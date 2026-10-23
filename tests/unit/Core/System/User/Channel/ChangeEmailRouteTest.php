<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Extension\ChangeEmailRouteExtension;
use Contena\Core\System\User\Channel\ChangeEmailRoute;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Validation\BuildValidationEvent;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ChangeEmailRoute::class)]
class ChangeEmailRouteTest extends TestCase
{
    public function testGetDecoratedThrowsDecorationPatternException(): void
    {
        $route = new ChangeEmailRoute(
            StaticEntityRepository::of(UserCollection::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            StaticEntityRepository::of(UserRecoveryCollection::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectExceptionObject(new DecorationPatternException(ChangeEmailRoute::class));
        $route->getDecorated();
    }

    public function testChangesEmailAndDeletesRecoveryEntities(): void
    {
        $userId = 'user-id';
        $context = Generator::generateChannelContext();
        $user = new UserEntity();
        $user->setId($userId);

        $userRepository = StaticEntityRepository::of(UserCollection::class);
        $recoveryRepository = StaticEntityRepository::of(UserRecoveryCollection::class, [['recovery-id']]);
        $route = new ChangeEmailRoute(
            $userRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            $recoveryRepository,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $result = $route->change(new RequestDataBag([
            'email' => 'new@example.com',
            'emailConfirmation' => 'new@example.com',
            'password' => 'password',
        ]), $context, $user);

        static::assertSame(200, $result->getStatusCode());
        static::assertSame([[['id' => $userId, 'email' => 'new@example.com']]], $userRepository->updates);
        static::assertSame([[['id' => 'recovery-id']]], $recoveryRepository->deletes);
    }

    public function testRejectsDifferentEmailConfirmationBeforeUpdatingUser(): void
    {
        $userRepository = StaticEntityRepository::of(UserCollection::class);
        $context = Generator::generateChannelContext();
        $route = new ChangeEmailRoute(
            $userRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            StaticEntityRepository::of(UserRecoveryCollection::class, [[]]),
            new ExtensionDispatcher(new EventDispatcher()),
        );
        $user = new UserEntity();
        $user->setId('user-id');

        $data = [
            'email' => 'new@example.com',
            'emailConfirmation' => 'different@example.com',
            'password' => 'password',
        ];
        $this->expectExceptionObject(new ConstraintViolationException(
            new ConstraintViolationList([
                new ConstraintViolation(
                    'This value should be equal to different@example.com.',
                    'This value should be equal to {{ compared_value }}.',
                    [],
                    '',
                    'email',
                    'new@example.com',
                ),
            ]),
            $data,
        ));

        $route->change(new RequestDataBag($data), $context, $user);
    }

    public function testAllowsCustomValidationDefinitionWithoutEmailEqualityConstraint(): void
    {
        $userRepository = StaticEntityRepository::of(UserCollection::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (BuildValidationEvent $event): object {
                $event->getDefinition()->set('email', new NotBlank());

                return $event;
            });

        $route = new ChangeEmailRoute(
            $userRepository,
            $eventDispatcher,
            static::createStub(DataValidator::class),
            StaticEntityRepository::of(UserRecoveryCollection::class, [[]]),
            new ExtensionDispatcher(new EventDispatcher()),
        );
        $user = new UserEntity();
        $user->setId('user-id');

        $route->change(new RequestDataBag([
            'email' => 'new@example.com',
            'emailConfirmation' => 'different@example.com',
            'password' => 'password',
        ]), Generator::generateChannelContext(), $user);

        static::assertCount(1, $userRepository->updates);
    }

    public function testPublishesExtension(): void
    {
        $requestDataBag = new RequestDataBag();
        $context = Generator::generateChannelContext();
        $user = new UserEntity();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('change-email-route.change.pre', static function (ChangeEmailRouteExtension $extension) use ($requestDataBag, $context, $user, $response): void {
            static::assertSame(['requestDataBag' => $requestDataBag, 'context' => $context, 'user' => $user], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ChangeEmailRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            static::createStub(EntityRepository::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->change($requestDataBag, $context, $user));
    }
}
