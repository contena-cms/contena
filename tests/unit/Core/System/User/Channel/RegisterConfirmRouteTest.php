<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\User\Channel\RegisterConfirmRoute;
use Contena\Core\System\User\Channel\UserResponse;
use Contena\Core\System\User\Exception\UserAlreadyConfirmedException;
use Contena\Core\System\User\Extension\RegisterConfirmRouteExtension;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(RegisterConfirmRoute::class)]
class RegisterConfirmRouteTest extends TestCase
{
    protected ChannelContext&Stub $context;

    protected EventDispatcherInterface&Stub $eventDispatcher;

    /**
     * @var EntityRepository<UserCollection>&MockObject
     */
    protected EntityRepository&MockObject $userRepository;

    protected DataValidator&Stub $validator;

    protected ChannelContextPersister&Stub $channelContextPersister;

    protected ChannelContextServiceInterface&Stub $channelContextService;

    protected RegisterConfirmRoute $route;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = static::createStub(ChannelContext::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->userRepository = $this->createMock(EntityRepository::class);
        $this->validator = static::createStub(DataValidator::class);
        $this->channelContextPersister = static::createStub(ChannelContextPersister::class);

        $newChannelContext = static::createStub(ChannelContext::class);
        $newChannelContext->method('getUser')->willReturn(new UserEntity());

        $this->channelContextService = static::createStub(ChannelContextServiceInterface::class);
        $this->channelContextService
            ->method('get')
            ->willReturn($newChannelContext);

        $this->route = $this->createRoute();
    }

    public function testConfirmUser(): void
    {
        $user = $this->mockUser();

        $this->userRepository->expects($this->exactly(2))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'user',
                    1,
                    new UserCollection([$user]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $confirmResult = $this->route->confirm($this->mockRequestDataBag(), $this->context);

        static::assertTrue($confirmResult->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testConfirmUserNotDoubleOptIn(): void
    {
        $user = $this->mockUser();
        $user->setDoubleOptInRegistration(false);

        $this->userRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'user',
                    1,
                    new UserCollection([$user]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $validator = $this->createMock(DataValidator::class);
        $validator->expects($this->once())
            ->method('validate')
            ->willReturnCallback(static function (array $data, DataValidationDefinition $definition): void {
                $properties = $definition->getProperties();
                static::assertArrayHasKey('doubleOptInRegistration', $properties);
                static::assertContainsOnlyInstancesOf(IsTrue::class, $properties['doubleOptInRegistration']);

                static::assertFalse($data['doubleOptInRegistration']);

                throw new ConstraintViolationException(new ConstraintViolationList(), $data);
            });

        $route = $this->createRoute($validator);

        static::expectException(ConstraintViolationException::class);
        $route->confirm($this->mockRequestDataBag(), $this->context);
    }

    public function testConfirmActivatedUser(): void
    {
        $user = $this->mockUser();
        $user->setActive(true);
        $user->setDoubleOptInConfirmDate(new \DateTime());

        $this->userRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'user',
                    1,
                    new UserCollection([$user]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        static::expectException(UserAlreadyConfirmedException::class);
        $this->route->confirm($this->mockRequestDataBag(), $this->context);
    }

    public function testConfirmConfirmedUser(): void
    {
        $user = $this->mockUser();
        $user->setDoubleOptInConfirmDate(new \DateTime());

        $this->userRepository->expects($this->once())
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'user',
                    1,
                    new UserCollection([$user]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        static::expectException(UserAlreadyConfirmedException::class);
        $this->route->confirm($this->mockRequestDataBag(), $this->context);
    }

    public function testPublishesExtension(): void
    {
        $dataBag = new RequestDataBag();
        $response = new UserResponse(new UserEntity());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('register-confirm-route.confirm.pre', function (RegisterConfirmRouteExtension $extension) use ($dataBag, $response): void {
            static::assertSame(['dataBag' => $dataBag, 'context' => $this->context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $this->userRepository->expects($this->never())->method('search');

        $route = new RegisterConfirmRoute(
            $this->userRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            static::createStub(ChannelContextPersister::class),
            static::createStub(ChannelContextServiceInterface::class),
            static::createStub(ClockInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->confirm($dataBag, $this->context));
    }

    protected function mockUser(): UserEntity
    {
        $user = new UserEntity();
        $user->setId('user-1');
        $user->setActive(false);
        $user->setEmail('test@test.test');
        $user->setHash('hash');
        $user->setGuest(false);
        $user->setDoubleOptInRegistration(true);
        $user->setDoubleOptInEmailSentDate(new \DateTime());

        return $user;
    }

    protected function mockRequestDataBag(): RequestDataBag
    {
        return new RequestDataBag([
            'hash' => 'hash',
            'em' => Hasher::hash('test@test.test', 'sha1'),
        ]);
    }

    private function createRoute(?DataValidator $validator = null): RegisterConfirmRoute
    {
        return new RegisterConfirmRoute(
            $this->userRepository,
            $this->eventDispatcher,
            $validator ?? $this->validator,
            $this->channelContextPersister,
            $this->channelContextService,
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher())
        );
    }
}
