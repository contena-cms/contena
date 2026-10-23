<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Extension\ConvertGuestRouteExtension;
use Contena\Core\System\User\Channel\ConvertGuestRoute;
use Contena\Core\System\User\Validation\Constraint\UserEmailUnique;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(ConvertGuestRoute::class)]
class ConvertGuestRouteTest extends TestCase
{
    private ConvertGuestRoute $route;

    /**
     * @var StaticEntityRepository<UserCollection>
     */
    private StaticEntityRepository $userRepository;

    private EventDispatcherInterface&Stub $eventDispatcher;

    private DataValidator&Stub $validator;

    private DataValidationFactoryInterface&Stub $passwordValidationFactory;

    private ChannelContext $channelContext;

    private UserEntity $user;

    protected function setUp(): void
    {
        $this->userRepository = new StaticEntityRepository([]);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->validator = static::createStub(DataValidator::class);
        $this->passwordValidationFactory = static::createStub(DataValidationFactoryInterface::class);

        $this->route = $this->buildRoute();

        $this->channelContext = Generator::generateChannelContext();

        $this->user = new UserEntity();
        $this->user->setId('test-user-id');
        $this->user->setEmail('test@example.com');
        $this->user->setGuest(true);
    }

    public function testConvertGuestSuccess(): void
    {
        $requestDataBag = new RequestDataBag(['password' => 'new-password']);

        $passwordDefinition = new DataValidationDefinition('user.password');
        $passwordDefinition->add('password', new NotBlank());

        $passwordValidationFactory = $this->createMock(DataValidationFactoryInterface::class);
        $passwordValidationFactory->expects($this->once())
            ->method('create')
            ->with($this->channelContext)
            ->willReturn($passwordDefinition);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                static::anything(),
                static::equalTo('framework.validation.user.guest.convert')
            );

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with([
                'email' => 'test@example.com',
                'password' => 'new-password',
            ], static::callback(function (DataValidationDefinition $definition) {
                static::assertSame('user.guest.convert', $definition->getName());
                static::assertEquals([
                    'password' => [new NotBlank()],
                    'email' => [new UserEmailUnique(channelContext: $this->channelContext)],
                ], $definition->getProperties());

                return true;
            }));

        $route = $this->buildRoute($eventDispatcher, $validator, $passwordValidationFactory);
        $route->convertGuest($requestDataBag, $this->channelContext, $this->user);

        static::assertSame([[[
            'id' => 'test-user-id',
            'email' => 'test@example.com',
            'guest' => false,
            'password' => 'new-password',
        ]]], $this->userRepository->updates);
    }

    public function testConvertGuestFailsForRegisteredUser(): void
    {
        $this->user->setGuest(false);
        $requestDataBag = new RequestDataBag(['password' => 'new-password']);

        $this->expectExceptionObject(UserException::registeredUserCannotBeConverted('test-user-id'));

        $this->route->convertGuest($requestDataBag, $this->channelContext, $this->user);
    }

    public function testConvertGuestFailsWithValidationErrors(): void
    {
        $requestDataBag = new RequestDataBag(['password' => '']);
        $violations = new ConstraintViolationException(
            new ConstraintViolationList(),
            [
                'id' => 'test-user-id',
                'guest' => false,
                'password' => '',
                'email' => 'test@example.com']
        );

        $passwordDefinition = new DataValidationDefinition('user.password');
        $passwordDefinition->add('password', new NotBlank());

        $passwordValidationFactory = $this->createMock(DataValidationFactoryInterface::class);
        $passwordValidationFactory->expects($this->once())
            ->method('create')
            ->with($this->channelContext)
            ->willReturn($passwordDefinition);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch');

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->with([
                'email' => 'test@example.com',
                'password' => '',
            ], static::callback(function (DataValidationDefinition $definition) {
                static::assertSame('user.guest.convert', $definition->getName());
                static::assertEquals([
                    'password' => [new NotBlank()],
                    'email' => [new UserEmailUnique(channelContext: $this->channelContext)],
                ], $definition->getProperties());

                return true;
            }))
            ->willThrowException($violations);

        $route = $this->buildRoute($eventDispatcher, $validator, $passwordValidationFactory);

        $this->expectExceptionObject($violations);

        try {
            $route->convertGuest($requestDataBag, $this->channelContext, $this->user);
        } finally {
            static::assertCount(0, $this->userRepository->updates);
        }
    }

    public function testPublishesExtension(): void
    {
        $requestDataBag = new RequestDataBag();
        $additionalValidationDefinitions = new DataValidationDefinition();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('convert-guest-route.convert-guest.pre', function (ConvertGuestRouteExtension $extension) use ($requestDataBag, $additionalValidationDefinitions, $response): void {
            static::assertSame(['requestDataBag' => $requestDataBag, 'context' => $this->channelContext, 'user' => $this->user, 'additionalValidationDefinitions' => $additionalValidationDefinitions], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ConvertGuestRoute(
            $this->userRepository,
            $this->eventDispatcher,
            $this->validator,
            $this->passwordValidationFactory,
            static::createStub(RequestStack::class),
            static::createStub(RateLimiter::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->convertGuest($requestDataBag, $this->channelContext, $this->user, $additionalValidationDefinitions));
    }

    private function buildRoute(
        ?EventDispatcherInterface $eventDispatcher = null,
        ?DataValidator $validator = null,
        ?DataValidationFactoryInterface $passwordValidationFactory = null
    ): ConvertGuestRoute {
        return new ConvertGuestRoute(
            $this->userRepository,
            $eventDispatcher ?? $this->eventDispatcher,
            $validator ?? $this->validator,
            $passwordValidationFactory ?? $this->passwordValidationFactory,
            static::createStub(RequestStack::class),
            static::createStub(RateLimiter::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );
    }
}
