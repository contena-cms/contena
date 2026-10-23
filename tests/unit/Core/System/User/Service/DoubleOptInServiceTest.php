<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Event\UserDoubleOptInRegistrationEvent;
use Contena\Core\System\User\Event\DoubleOptInGuestOrderEvent;
use Contena\Core\System\User\Service\DoubleOptInService;
use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(DoubleOptInService::class)]
class DoubleOptInServiceTest extends TestCase
{
    private EventDispatcher $eventDispatcher;

    /**
     * @var StaticEntityRepository<UserCollection>
     */
    private StaticEntityRepository $userRepository;

    /**
     * @var StaticEntityRepository<ChannelDomainCollection>
     */
    private StaticEntityRepository $channelDomainRepository;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->userRepository = new StaticEntityRepository([]);
        $this->channelDomainRepository = new StaticEntityRepository([]);
    }

    public function testSendDoubleOptInMailDispatchesRegistrationEvent(): void
    {
        $user = $this->createUserEntity('testhash', false);
        $context = Generator::generateChannelContext();

        $dispatched = null;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService()->sendDoubleOptInMail($user, $context, 'https://shop.example.com');

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://shop.example.com', $dispatched->getConfirmUrl());
    }

    public function testSendDoubleOptInMailDispatchesGuestEvent(): void
    {
        $user = $this->createUserEntity('testhash', true);
        $context = Generator::generateChannelContext();

        $dispatched = null;
        $this->eventDispatcher->addListener(
            DoubleOptInGuestOrderEvent::class,
            static function (DoubleOptInGuestOrderEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService()->sendDoubleOptInMail($user, $context, 'https://shop.example.com');

        static::assertInstanceOf(DoubleOptInGuestOrderEvent::class, $dispatched);
    }

    public function testSendDoubleOptInMailWithRedirectTo(): void
    {
        $user = $this->createUserEntity('testhash', false);
        $context = Generator::generateChannelContext();

        $dispatched = null;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService()->sendDoubleOptInMail($user, $context, 'https://shop.example.com', 'account');

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringContainsString('redirectTo=account', $dispatched->getConfirmUrl());
    }

    public function testSendDoubleOptInMailWithRedirectToAndParameters(): void
    {
        $user = $this->createUserEntity('testhash', false);
        $context = Generator::generateChannelContext();

        $dispatched = null;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService()->sendDoubleOptInMail(
            $user,
            $context,
            'https://shop.example.com',
            'frontend.account.order.single.page',
            '{"orderId":"abc123"}'
        );

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringContainsString('redirectTo=frontend.account.order.single.page', $dispatched->getConfirmUrl());
        static::assertStringContainsString('orderId=abc123', $dispatched->getConfirmUrl());
    }

    public function testSendDoubleOptInMailUsesCustomConfirmUrl(): void
    {
        $user = $this->createUserEntity('customhash', false);
        $context = Generator::generateChannelContext();

        $dispatched = null;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService([
            'core.loginRegistration.confirmationUrl' => '/custom/confirm?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%',
        ])->sendDoubleOptInMail($user, $context, 'https://shop.example.com');

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringContainsString('/custom/confirm', $dispatched->getConfirmUrl());
        static::assertStringContainsString('customhash', $dispatched->getConfirmUrl());
    }

    public function testResendDoubleOptInMailDisabledWhenIntervalIsZero(): void
    {
        $user = $this->createUserEntity('testhash', false);
        $user->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-10 days'));
        $context = Generator::generateChannelContext();

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 0,
        ])->resendDoubleOptInMail($user, $context);

        static::assertFalse($eventDispatched);
        static::assertCount(0, $this->userRepository->updates);
    }

    public function testResendDoubleOptInMailDisabledWhenIntervalNotConfigured(): void
    {
        $user = $this->createUserEntity('testhash', false);
        $user->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-10 days'));
        $context = Generator::generateChannelContext();

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $this->createService()->resendDoubleOptInMail($user, $context);

        static::assertFalse($eventDispatched);
        static::assertCount(0, $this->userRepository->updates);
    }

    public function testResendDoubleOptInMailSkipsWhenNoSentDate(): void
    {
        $user = $this->createUserEntity('testhash', false);
        // no doubleOptInEmailSentDate set
        $context = Generator::generateChannelContext();

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($user, $context);

        static::assertFalse($eventDispatched);
    }

    public function testResendDoubleOptInMailSkipsWhenWithinCooldown(): void
    {
        $user = $this->createUserEntity('testhash', false);
        $user->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-1 hour'));
        $context = Generator::generateChannelContext();

        $eventDispatched = false;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function () use (&$eventDispatched): void {
                $eventDispatched = true;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($user, $context);

        static::assertFalse($eventDispatched);
    }

    public function testResendDoubleOptInMailSendsWhenCooldownElapsed(): void
    {
        $user = $this->createUserEntity('testhash', false);
        $user->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        $context = Generator::generateChannelContext();

        $dispatched = null;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
            'core.loginRegistration.doubleOptInDomain' => 'https://shop.example.com',
        ])->resendDoubleOptInMail($user, $context);

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertCount(1, $this->userRepository->updates);
        static::assertSame($user->getId(), $this->userRepository->updates[0][0]['id']);
        static::assertInstanceOf(\DateTimeImmutable::class, $this->userRepository->updates[0][0]['doubleOptInEmailSentDate']);
    }

    public function testResolveDomainUrlUsesDomainFromContextDomainId(): void
    {
        $domainId = Uuid::randomHex();
        $domain = new ChannelDomainEntity();
        $domain->setId($domainId);
        $domain->setUrl('https://domain-by-id.example.com');
        $domain->setLanguageId(Uuid::randomHex());

        $channel = $this->createChannelWithDomains([$domain]);

        $user = $this->createUserEntity('testhash', false, Uuid::randomHex());
        $user->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        $context = Generator::generateChannelContext(domainId: $domainId, channel: $channel);

        $dispatched = null;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($user, $context);

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://domain-by-id.example.com', $dispatched->getConfirmUrl());
    }

    public function testResolveDomainUrlUsesDomainMatchingLanguageId(): void
    {
        $languageId = Uuid::randomHex();

        $matchingDomain = new ChannelDomainEntity();
        $matchingDomain->setId(Uuid::randomHex());
        $matchingDomain->setUrl('https://lang-domain.example.com');
        $matchingDomain->setLanguageId($languageId);

        $otherDomain = new ChannelDomainEntity();
        $otherDomain->setId(Uuid::randomHex());
        $otherDomain->setUrl('https://other-domain.example.com');
        $otherDomain->setLanguageId(Uuid::randomHex());

        $channel = $this->createChannelWithDomains([$matchingDomain, $otherDomain]);

        $user = $this->createUserEntity('testhash', false, $languageId);
        $user->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        // Pass domainId: null so the domainId path is not taken
        $context = Generator::generateChannelContext(domainId: null, channel: $channel);

        $dispatched = null;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($user, $context);

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://lang-domain.example.com', $dispatched->getConfirmUrl());
    }

    public function testResolveDomainUrlFallsBackToRepositoryWhenDomainsNoneMatch(): void
    {
        $collectionDomain = new ChannelDomainEntity();
        $collectionDomain->setId(Uuid::randomHex());
        $collectionDomain->setUrl('https://collection-domain.example.com');
        $collectionDomain->setLanguageId(Uuid::randomHex());

        $channel = $this->createChannelWithDomains([$collectionDomain]);

        // User language and context domainId both do not match any domain in the collection
        $user = $this->createUserEntity('testhash', false, Uuid::randomHex());
        $user->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        $context = Generator::generateChannelContext(domainId: null, channel: $channel);

        $repoDomain = new ChannelDomainEntity();
        $repoDomain->setId(Uuid::randomHex());
        $repoDomain->setUrl('https://repo-fallback.example.com');

        $this->channelDomainRepository = new StaticEntityRepository([
            new ChannelDomainCollection([$repoDomain]),
        ]);

        $dispatched = null;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($user, $context);

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://repo-fallback.example.com', $dispatched->getConfirmUrl());
    }

    public function testResendDoubleOptInMailFallsBackToChannelDomainRepository(): void
    {
        $user = $this->createUserEntity('testhash', false);
        $user->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        $context = Generator::generateChannelContext();

        $domain = new ChannelDomainEntity();
        $domain->setId(Uuid::randomHex());
        $domain->setUrl('https://fallback-domain.example.com');

        $this->channelDomainRepository = new StaticEntityRepository([
            new ChannelDomainCollection([$domain]),
        ]);

        $dispatched = null;
        $this->eventDispatcher->addListener(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            }
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($user, $context);

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://fallback-domain.example.com', $dispatched->getConfirmUrl());
    }

    public function testMapUserDoubleOptInDataReturnUnchangedWhenDoubleOptInDisabled(): void
    {
        $context = Generator::generateChannelContext();
        $input = ['guest' => false, 'email' => 'test@example.com'];

        $result = $this->createService([
            'core.loginRegistration.doubleOptInRegistration' => false,
        ])->mapUserDoubleOptInData($input, $context);

        static::assertSame($input, $result);
    }

    public function testMapUserDoubleOptInDataSetsFieldsForRegistration(): void
    {
        $context = Generator::generateChannelContext();
        $input = ['guest' => false, 'email' => 'test@example.com'];

        $result = $this->createService([
            'core.loginRegistration.doubleOptInRegistration' => true,
        ])->mapUserDoubleOptInData($input, $context);

        static::assertTrue($result['doubleOptInRegistration']);
        static::assertInstanceOf(\DateTimeImmutable::class, $result['doubleOptInEmailSentDate']);
        static::assertIsString($result['hash']);
        static::assertTrue(Uuid::isValid($result['hash']));
    }

    public function testMapUserDoubleOptInDataSetsFieldsForGuestOrder(): void
    {
        $context = Generator::generateChannelContext();
        $input = ['guest' => true, 'email' => 'guest@example.com'];

        $result = $this->createService([
            'core.loginRegistration.doubleOptInGuestOrder' => true,
        ])->mapUserDoubleOptInData($input, $context);

        static::assertTrue($result['doubleOptInRegistration']);
        static::assertInstanceOf(\DateTimeImmutable::class, $result['doubleOptInEmailSentDate']);
        static::assertIsString($result['hash']);
        static::assertTrue(Uuid::isValid($result['hash']));
    }

    /**
     * @param array<string, mixed> $systemConfig
     */
    private function createService(array $systemConfig = []): DoubleOptInService
    {
        return new DoubleOptInService(
            $this->userRepository,
            $this->eventDispatcher,
            new StaticSystemConfigService($systemConfig),
            $this->channelDomainRepository,
            new NativeClock(),
        );
    }

    private function createUserEntity(string $hash, bool $guest, ?string $languageId = null): UserEntity
    {
        $user = new UserEntity();
        $user->setId(Uuid::randomHex());
        $user->setHash($hash);
        $user->setGuest($guest);
        $user->setEmail('test@example.com');
        $user->setLanguageId($languageId ?? Defaults::LANGUAGE_SYSTEM);

        return $user;
    }

    /**
     * @param ChannelDomainEntity[] $domains
     */
    private function createChannelWithDomains(array $domains): ChannelEntity
    {
        $channel = new ChannelEntity();
        $channel->setId(TestDefaults::CHANNEL);
        $channel->setNavigationCategoryId(Generator::NAVIGATION_CATEGORY);
        $channel->setTaxCalculationType(Generator::TAX_CALCULATION_TYPE);
        $channel->setNavigationCategoryDepth(2);
        $channel->setDomains(new ChannelDomainCollection($domains));

        return $channel;
    }
}
