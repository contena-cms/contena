<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Channel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\WriteConstraintViolationException;
use Contena\Core\System\Channel\Context\CartRestorer;
use Contena\Core\System\User\Channel\AbstractSwitchDefaultAddressRoute;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\System\User\Event\UserBeforeLoginEvent;
use Contena\Core\System\User\Event\UserLoginEvent;
use Contena\Core\System\User\Exception\BadCredentialsException;
use Contena\Core\System\User\Exception\PasswordPoliciesUpdatedException;
use Contena\Core\System\User\Exception\UserNotFoundByIdException;
use Contena\Core\System\User\Exception\UserOptinNotCompletedException;
use Contena\Core\System\User\Password\LegacyPasswordVerifier;
use Contena\Core\System\User\Service\DoubleOptInService;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(AccountService::class)]
class AccountServiceTest extends TestCase
{
    public function testLoginByValidCredentials(): void
    {
        $channelContext = Generator::generateChannelContext();
        $user = $channelContext->getUser();
        static::assertNotNull($user);
        $user->setActive(true);
        $user->setGuest(false);
        $user->setPassword(TestDefaults::HASHED_PASSWORD);
        $user->setEmail('foo@bar.de');
        $user->setDoubleOptInRegistration(false);

        $userRepository = new StaticEntityRepository([
        ]);

        $loggedinChannelContext = Generator::generateChannelContext();
        $cartRestorer = $this->createMock(CartRestorer::class);
        $cartRestorer->expects($this->once())
            ->method('restore')
            ->willReturn($loggedinChannelContext);

        $beforeLoginEventCalled = false;
        $loginEventCalled = false;

        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            UserBeforeLoginEvent::class,
            static function (UserBeforeLoginEvent $event) use ($channelContext, &$beforeLoginEventCalled): void {
                $beforeLoginEventCalled = true;
                static::assertSame('foo@bar.de', $event->getEmail());
                static::assertSame($channelContext, $event->getChannelContext());
            },
        );

        $eventDispatcher->addListener(
            UserLoginEvent::class,
            static function (UserLoginEvent $event) use ($user, $loggedinChannelContext, &$loginEventCalled): void {
                $loginEventCalled = true;
                static::assertSame($user, $event->getUser());
                static::assertSame($loggedinChannelContext, $event->getChannelContext());
                static::assertSame($loggedinChannelContext->getToken(), $event->getContextToken());
            },
        );

        $accountService = new AccountService(
            $userRepository,
            $eventDispatcher,
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            $cartRestorer,
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $token = $accountService->loginByCredentials('foo@bar.de', 'contena', $channelContext);
        static::assertSame($loggedinChannelContext->getToken(), $token);
        static::assertTrue($beforeLoginEventCalled);
        static::assertTrue($loginEventCalled);
        static::assertCount(1, $userRepository->updates);
        static::assertCount(1, $userRepository->updates[0]);
        static::assertIsArray($userRepository->updates[0][0]);
        static::assertCount(2, $userRepository->updates[0][0]);
        static::assertSame($user->getId(), $userRepository->updates[0][0]['id']);
        static::assertInstanceOf(\DateTimeImmutable::class, $userRepository->updates[0][0]['lastLogin']);
    }

    public function testLoginFailsByInvalidCredentials(): void
    {
        $channelContext = Generator::generateChannelContext();
        $user = $channelContext->getUser();
        static::assertNotNull($user);
        $user->setActive(true);
        $user->setGuest(false);
        $user->setPassword(TestDefaults::HASHED_PASSWORD);
        $user->setEmail('foo@bar.de');
        $user->setDoubleOptInRegistration(false);

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserDefinition::ENTITY_NAME,
                1,
                new UserCollection([$user]),
                null,
                new Criteria(),
                $channelContext->getContext()
            ));

        $cartRestorer = $this->createMock(CartRestorer::class);
        $cartRestorer->expects($this->never())
            ->method('restore');

        $accountService = new AccountService(
            $userRepository,
            new EventDispatcher(),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            $cartRestorer,
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectException(BadCredentialsException::class);
        $accountService->loginByCredentials('foo@bar.de', 'invalidPassword', $channelContext);
    }

    public function testGetUserByLoginThrowsBadCredentialsWhenEmailNotFound(): void
    {
        $channelContext = Generator::generateChannelContext();

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserDefinition::ENTITY_NAME,
                0,
                new UserCollection(),
                null,
                new Criteria(),
                $channelContext->getContext()
            ));

        $accountService = new AccountService(
            $userRepository,
            new EventDispatcher(),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectException(BadCredentialsException::class);
        $accountService->getUserByLogin('unknown@example.com', 'any-password', $channelContext);
    }

    public function testGetUserByIdThrowsPasswordPoliciesChangedException(): void
    {
        $channelContext = Generator::generateChannelContext();
        $user = $channelContext->getUser();
        static::assertNotNull($user);
        $user->setActive(true);
        $user->setGuest(false);
        $user->setLegacyPassword('foo');
        $user->setLegacyEncoder('bar');

        $legacyPasswordVerifier = $this->createMock(LegacyPasswordVerifier::class);
        $legacyPasswordVerifier->expects($this->once())
            ->method('verify')
            ->with('password', $user)
            ->willReturn(true);

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserDefinition::ENTITY_NAME,
                1,
                new UserCollection([$user]),
                null,
                new Criteria(),
                $channelContext->getContext()
            ));

        $exception = new WriteConstraintViolationException(new ConstraintViolationList([new ConstraintViolation('', '', [], '', '/password', '')]), '/');
        $writeException = new WriteException();
        $writeException->add($exception);

        $userRepository->expects($this->once())
            ->method('update')
            ->with([[
                'id' => $user->getId(),
                'password' => 'password',
                'legacyPassword' => null,
                'legacyEncoder' => null,
            ]], $channelContext->getContext())
            ->willThrowException($writeException);

        $accountService = new AccountService(
            $userRepository,
            static::createStub(EventDispatcherInterface::class),
            $legacyPasswordVerifier,
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectExceptionObject(new PasswordPoliciesUpdatedException());
        $accountService->getUserByLogin('admin_user', 'password', $channelContext);
    }

    public function testGetUserByIdIgnoresOtherWriteViolations(): void
    {
        $channelContext = Generator::generateChannelContext();
        $user = $channelContext->getUser();
        static::assertNotNull($user);
        $user->setActive(true);
        $user->setGuest(false);
        $user->setLegacyPassword('foo');
        $user->setLegacyEncoder('bar');

        $legacyPasswordVerifier = $this->createMock(LegacyPasswordVerifier::class);
        $legacyPasswordVerifier->expects($this->once())
            ->method('verify')
            ->with('password', $user)
            ->willReturn(true);

        $userRepository = $this->createMock(EntityRepository::class);
        $userRepository->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserDefinition::ENTITY_NAME,
                1,
                new UserCollection([$user]),
                null,
                new Criteria(),
                $channelContext->getContext()
            ));

        $exception = UserException::badCredentials();
        $writeException = new WriteException();
        $writeException->add($exception);

        $userRepository->expects($this->once())
            ->method('update')
            ->with([[
                'id' => $user->getId(),
                'password' => 'password',
                'legacyPassword' => null,
                'legacyEncoder' => null,
            ]], $channelContext->getContext())
            ->willThrowException($writeException);

        $accountService = new AccountService(
            $userRepository,
            static::createStub(EventDispatcherInterface::class),
            $legacyPasswordVerifier,
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectException(WriteException::class);
        $accountService->getUserByLogin('admin_user', 'password', $channelContext);
    }

    public function testSetDefaultBillingAddress(): void
    {
        $context = Generator::generateChannelContext();
        $user = $context->getUser();

        static::assertNotNull($user);

        $switcher = $this->createMock(AbstractSwitchDefaultAddressRoute::class);
        $switcher
            ->expects($this->once())
            ->method('swap')
            ->with('billing-address-id', AbstractSwitchDefaultAddressRoute::TYPE_BILLING, $context, $user);

        $accountService = new AccountService(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            $switcher,
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $accountService->setDefaultBillingAddress('billing-address-id', $context, $user);
    }

    public function testSetDefaultShippingAddress(): void
    {
        $context = Generator::generateChannelContext();
        $user = $context->getUser();

        static::assertNotNull($user);

        $switcher = $this->createMock(AbstractSwitchDefaultAddressRoute::class);
        $switcher
            ->expects($this->once())
            ->method('swap')
            ->with('shipping-address-id', AbstractSwitchDefaultAddressRoute::TYPE_SHIPPING, $context, $user);

        $accountService = new AccountService(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            $switcher,
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $accountService->setDefaultShippingAddress('shipping-address-id', $context, $user);
    }

    public function testLoginById(): void
    {
        $context = Generator::generateChannelContext();

        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setActive(true);
        $user->setBoundChannelId($context->getChannel()->getId());
        $user->setEmail('foo@bar.de');

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserDefinition::ENTITY_NAME,
                1,
                new UserCollection([$user]),
                null,
                new Criteria(),
                $context->getContext()
            ));

        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::callback(static function ($event) use ($context, $user): bool {
                if ($event instanceof UserBeforeLoginEvent) {
                    static::assertSame($context, $event->getChannelContext());
                    static::assertSame($user->getEmail(), $event->getEmail());

                    return true;
                }

                if ($event instanceof UserLoginEvent) {
                    static::assertSame($user, $event->getUser());

                    return true;
                }

                return false;
            }));

        $accountService = new AccountService(
            $repo,
            $dispatcher,
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $accountService->loginById($user->getId(), $context);
    }

    public function testLoginByIdWithNonValidId(): void
    {
        $context = Generator::generateChannelContext();

        $accountService = new AccountService(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectException(BadCredentialsException::class);

        $accountService->loginById('foo', $context);
    }

    public function testLoginByIdNotFound(): void
    {
        $context = Generator::generateChannelContext();

        $repo = $this->createMock(EntityRepository::class);
        $repo
            ->expects($this->once())
            ->method('search')
            ->willReturn(new EntitySearchResult(
                UserDefinition::ENTITY_NAME,
                0,
                new UserCollection(),
                null,
                new Criteria(),
                $context->getContext()
            ));

        $accountService = new AccountService(
            $repo,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectException(UserNotFoundByIdException::class);

        $accountService->loginById(Uuid::randomHex(), $context);
    }

    public function testPasswordTooLongThrowsBadCredentials(): void
    {
        $channelContext = Generator::generateChannelContext();
        $accountService = new AccountService(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            static::createStub(DoubleOptInService::class),
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        static::expectException(BadCredentialsException::class);

        $accountService->loginByCredentials('foo@bar.de', \str_repeat('a', PasswordHasherInterface::MAX_PASSWORD_LENGTH + 1), $channelContext);
    }

    public function testGetUserByLoginWithUnconfirmedDoubleOptIn(): void
    {
        $channelContext = Generator::generateChannelContext();
        $user = $channelContext->getUser();
        static::assertNotNull($user);
        $user->setActive(true);
        $user->setGuest(false);
        $user->setPassword(TestDefaults::HASHED_PASSWORD);
        $user->setEmail('foo@bar.de');
        $user->setDoubleOptInRegistration(true);

        $userRepository = new StaticEntityRepository([
        ]);

        $doubleOptInService = $this->createMock(DoubleOptInService::class);
        $doubleOptInService->expects($this->once())
            ->method('resendDoubleOptInMail')
            ->with($user, $channelContext);

        $accountService = new AccountService(
            $userRepository,
            new EventDispatcher(),
            static::createStub(LegacyPasswordVerifier::class),
            static::createStub(AbstractSwitchDefaultAddressRoute::class),
            static::createStub(CartRestorer::class),
            $doubleOptInService,
            new NativeClock(),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectException(UserOptinNotCompletedException::class);
        $accountService->getUserByLogin('foo@bar.de', 'contena', $channelContext);
    }
}
