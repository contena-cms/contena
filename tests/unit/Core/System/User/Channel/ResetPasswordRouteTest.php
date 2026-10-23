<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\Framework\Validation\DataValidationFactoryInterface;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryCollection;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryEntity;
use Contena\Core\System\User\Channel\ResetPasswordRoute;
use Contena\Core\System\User\Extension\ResetPasswordRouteExtension;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Test\Generator;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ResetPasswordRoute::class)]
class ResetPasswordRouteTest extends TestCase
{
    public function testResetsAllRateLimitersOnPasswordReset(): void
    {
        $email = 'User@Example.com';
        $ip = '10.0.0.1';
        $hash = 'valid-hash';
        $userId = Uuid::randomHex();
        $recoveryId = Uuid::randomHex();
        $expectedEmailKey = strtolower($email);
        $expectedCombinedKey = $expectedEmailKey . '-' . $ip;

        $user = new UserEntity();
        $user->setId($userId);
        $user->setEmail($email);
        $user->setDoubleOptInRegistration(false);

        $recovery = new UserRecoveryEntity();
        $recovery->setId($recoveryId);
        $recovery->setUser($user);
        $recovery->setCreatedAt(new \DateTimeImmutable());

        $recoveryCollection = new UserRecoveryCollection([$recovery]);

        $userRecoveryRepository = static::createStub(EntityRepository::class);
        $userRecoveryRepository->method('search')
            ->willReturn(new EntitySearchResult(
                'user_recovery',
                1,
                $recoveryCollection,
                null,
                new Criteria(),
                static::createStub(ChannelContext::class)->getContext()
            ));

        $userRepository = static::createStub(EntityRepository::class);

        $resetCalls = [];
        $resetIfConfiguredCalls = [];

        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter->expects($this->exactly(2))
            ->method('reset')
            ->willReturnCallback(function (string $route, string $key) use (&$resetCalls): void {
                $resetCalls[] = [$route, $key];
            });
        $rateLimiter->expects($this->exactly(2))
            ->method('resetIfConfigured')
            ->willReturnCallback(function (string $route, string $key) use (&$resetIfConfiguredCalls): void {
                $resetIfConfiguredCalls[] = [$route, $key];
            });

        $mainRequest = new Request(server: ['REMOTE_ADDR' => $ip]);
        $requestStack = new RequestStack();
        $requestStack->push($mainRequest);

        $passwordValidationFactory = static::createStub(DataValidationFactoryInterface::class);
        $passwordValidationFactory->method('update')->willReturn(new DataValidationDefinition());

        $route = new ResetPasswordRoute(
            $userRepository,
            $userRecoveryRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            $requestStack,
            $rateLimiter,
            $passwordValidationFactory,
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $context = $this->createMock(ChannelContext::class);
        $context->expects($this->exactly(5))->method('getContext')->willReturn(Context::createDefaultContext());

        $route->resetPassword(
            new RequestDataBag([
                'hash' => $hash,
                'newPassword' => 'newPass123!',
                'newPasswordConfirm' => 'newPass123!',
            ]),
            $context,
        );

        static::assertSame([
            [RateLimiter::LOGIN_ROUTE, $expectedCombinedKey],
            [RateLimiter::RESET_PASSWORD, $expectedCombinedKey],
        ], $resetCalls);

        static::assertSame([
            [RateLimiter::LOGIN_USER, $expectedEmailKey],
            [RateLimiter::LOGIN_CLIENT, $ip],
        ], $resetIfConfiguredCalls);
    }

    public function testConfirmsUnconfirmedDoubleOptInUserOnPasswordReset(): void
    {
        $now = new \DateTimeImmutable('2026-05-30 12:00:00');

        $user = $this->createUser();
        $user->setDoubleOptInRegistration(true);

        $userUpdate = $this->resetPasswordAndReturnUserUpdate($user, new MockClock($now));

        static::assertArrayHasKey('doubleOptInConfirmDate', $userUpdate);
        static::assertEquals($now, $userUpdate['doubleOptInConfirmDate']);
    }

    public function testDoesNotConfirmUserWithoutDoubleOptInRegistrationOnPasswordReset(): void
    {
        $user = $this->createUser();
        $user->setDoubleOptInRegistration(false);

        $userUpdate = $this->resetPasswordAndReturnUserUpdate($user);

        static::assertArrayNotHasKey('doubleOptInConfirmDate', $userUpdate);
    }

    public function testDoesNotOverwriteExistingDoubleOptInConfirmationOnPasswordReset(): void
    {
        $user = $this->createUser();
        $user->setDoubleOptInRegistration(true);
        $user->setDoubleOptInConfirmDate(new \DateTimeImmutable('2026-05-29 12:00:00'));

        $userUpdate = $this->resetPasswordAndReturnUserUpdate($user);

        static::assertArrayNotHasKey('doubleOptInConfirmDate', $userUpdate);
    }

    public function testPublishesExtension(): void
    {
        $data = new RequestDataBag();
        $context = Generator::generateChannelContext();
        $response = new SuccessResponse();

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('reset-password-route.reset-password.pre', static function (ResetPasswordRouteExtension $extension) use ($data, $context, $response): void {
            static::assertSame(['data' => $data, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ResetPasswordRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            static::createStub(RequestStack::class),
            static::createStub(RateLimiter::class),
            static::createStub(DataValidationFactoryInterface::class),
            static::createStub(ClockInterface::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->resetPassword($data, $context));
    }

    private function createUser(): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setEmail('user@example.com');

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function resetPasswordAndReturnUserUpdate(UserEntity $user, ?MockClock $clock = null): array
    {
        $hash = 'valid-hash';
        $recovery = new UserRecoveryEntity();
        $recovery->setId(Uuid::randomHex());
        $recovery->setHash($hash);
        $recovery->setUser($user);
        $recovery->setCreatedAt(new \DateTimeImmutable());

        $userRecoveryRepository = static::createStub(EntityRepository::class);
        $userRecoveryRepository->method('search')
            ->willReturn(new EntitySearchResult(
                'user_recovery',
                1,
                new UserRecoveryCollection([$recovery]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ));
        $userRecoveryRepository->method('delete')
            ->willReturn(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []));

        $userUpdate = null;
        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())
            ->method('update')
            ->willReturnCallback(function (array $updates) use (&$userUpdate): EntityWrittenContainerEvent {
                $userUpdate = $updates[0];

                return new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);
            });

        $passwordValidationFactory = static::createStub(DataValidationFactoryInterface::class);
        $passwordValidationFactory->method('update')->willReturn(new DataValidationDefinition());

        $route = new ResetPasswordRoute(
            $userRepository,
            $userRecoveryRepository,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(DataValidator::class),
            new RequestStack(),
            static::createStub(RateLimiter::class),
            $passwordValidationFactory,
            $clock ?? new MockClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $context = static::createStub(ChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $route->resetPassword(
            new RequestDataBag([
                'hash' => $hash,
                'newPassword' => 'newPass123!',
                'newPasswordConfirm' => 'newPass123!',
            ]),
            $context,
        );

        static::assertIsArray($userUpdate);

        return $userUpdate;
    }
}
