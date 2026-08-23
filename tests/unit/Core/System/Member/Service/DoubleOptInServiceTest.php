<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainEntity;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\Event\MemberDoubleOptInRegistrationEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Service\DoubleOptInService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
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
     * @var StaticEntityRepository<MemberCollection>
     */
    private StaticEntityRepository $memberRepository;

    /**
     * @var StaticEntityRepository<ChannelDomainCollection>
     */
    private StaticEntityRepository $channelDomainRepository;

    protected function setUp(): void
    {
        $this->eventDispatcher = new EventDispatcher();
        $this->memberRepository = new StaticEntityRepository([]);
        $this->channelDomainRepository = new StaticEntityRepository([]);
    }

    public function testSendDoubleOptInMailDispatchesRegistrationEvent(): void
    {
        $member = $this->createMemberEntity('testhash');
        $context = $this->createContext();
        $dispatched = null;
        $this->eventDispatcher->addListener(
            MemberDoubleOptInRegistrationEvent::class,
            static function (MemberDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            },
        );

        $this->createService()->sendDoubleOptInMail($member, $context, 'https://channel.example.com');

        static::assertInstanceOf(MemberDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://channel.example.com', $dispatched->getConfirmUrl());
    }

    public function testSendDoubleOptInMailWithRedirectTo(): void
    {
        $member = $this->createMemberEntity('testhash');
        $context = $this->createContext();
        $dispatched = null;
        $this->eventDispatcher->addListener(
            MemberDoubleOptInRegistrationEvent::class,
            static function (MemberDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            },
        );

        $this->createService()->sendDoubleOptInMail($member, $context, 'https://channel.example.com', 'account');

        static::assertInstanceOf(MemberDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringContainsString('redirectTo=account', $dispatched->getConfirmUrl());
    }

    public function testSendDoubleOptInMailUsesCustomConfirmUrl(): void
    {
        $member = $this->createMemberEntity('customhash');
        $context = $this->createContext();
        $dispatched = null;
        $this->eventDispatcher->addListener(
            MemberDoubleOptInRegistrationEvent::class,
            static function (MemberDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            },
        );

        $this->createService([
            'core.loginRegistration.confirmationUrl' => '/custom/confirm?em=%%HASHEDEMAIL%%&hash=%%SUBSCRIBEHASH%%',
        ])->sendDoubleOptInMail($member, $context, 'https://channel.example.com');

        static::assertInstanceOf(MemberDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringContainsString('/custom/confirm', $dispatched->getConfirmUrl());
        static::assertStringContainsString('customhash', $dispatched->getConfirmUrl());
    }

    public function testResendDoubleOptInMailDisabledWhenIntervalIsZero(): void
    {
        $member = $this->createMemberEntity('testhash');
        $member->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-10 days'));

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 0,
        ])->resendDoubleOptInMail($member, $this->createContext());

        static::assertEmpty($this->memberRepository->updates);
    }

    public function testResendDoubleOptInMailSkipsWhenNoSentDate(): void
    {
        $member = $this->createMemberEntity('testhash');

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($member, $this->createContext());

        static::assertEmpty($this->memberRepository->updates);
    }

    public function testResendDoubleOptInMailSkipsWhenWithinCooldown(): void
    {
        $member = $this->createMemberEntity('testhash');
        $member->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-1 hour'));

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
        ])->resendDoubleOptInMail($member, $this->createContext());

        static::assertEmpty($this->memberRepository->updates);
    }

    public function testResendDoubleOptInMailSendsWhenCooldownElapsed(): void
    {
        $member = $this->createMemberEntity('testhash');
        $member->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        $context = $this->createContext();
        $dispatched = null;
        $this->eventDispatcher->addListener(
            MemberDoubleOptInRegistrationEvent::class,
            static function (MemberDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            },
        );

        $this->createService([
            'core.loginRegistration.doubleOptInResendInterval' => 24,
            'core.loginRegistration.doubleOptInDomain' => 'https://channel.example.com',
        ])->resendDoubleOptInMail($member, $context);

        static::assertInstanceOf(MemberDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertCount(1, $this->memberRepository->updates);
        static::assertSame($member->getId(), $this->memberRepository->updates[0][0]['id']);
        static::assertInstanceOf(\DateTimeImmutable::class, $this->memberRepository->updates[0][0]['doubleOptInEmailSentDate']);
    }

    public function testResolveDomainUrlUsesDomainFromContextDomainId(): void
    {
        $domainId = Uuid::randomHex();
        $domain = $this->createDomain($domainId, 'https://domain-by-id.example.com', Uuid::randomHex());
        $context = $this->createContext($domainId, [$domain]);
        $member = $this->createMemberEntity('testhash', $domain->getLanguageId());
        $member->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        $dispatched = null;
        $this->eventDispatcher->addListener(
            MemberDoubleOptInRegistrationEvent::class,
            static function (MemberDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            },
        );

        $this->createService(['core.loginRegistration.doubleOptInResendInterval' => 24])
            ->resendDoubleOptInMail($member, $context);

        static::assertInstanceOf(MemberDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://domain-by-id.example.com', $dispatched->getConfirmUrl());
    }

    public function testResolveDomainUrlUsesDomainMatchingLanguageId(): void
    {
        $languageId = Uuid::randomHex();
        $matchingDomain = $this->createDomain(Uuid::randomHex(), 'https://lang-domain.example.com', $languageId);
        $otherDomain = $this->createDomain(Uuid::randomHex(), 'https://other-domain.example.com', Uuid::randomHex());
        $context = $this->createContext(null, [$matchingDomain, $otherDomain]);
        $member = $this->createMemberEntity('testhash', $languageId);
        $member->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        $dispatched = null;
        $this->eventDispatcher->addListener(
            MemberDoubleOptInRegistrationEvent::class,
            static function (MemberDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            },
        );

        $this->createService(['core.loginRegistration.doubleOptInResendInterval' => 24])
            ->resendDoubleOptInMail($member, $context);

        static::assertInstanceOf(MemberDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://lang-domain.example.com', $dispatched->getConfirmUrl());
    }

    public function testResendDoubleOptInMailFallsBackToChannelDomainRepository(): void
    {
        $context = $this->createContext();
        $member = $this->createMemberEntity('testhash');
        $member->setDoubleOptInEmailSentDate(new \DateTimeImmutable('-2 days'));
        $domain = $this->createDomain(Uuid::randomHex(), 'https://fallback-domain.example.com', Uuid::randomHex());
        $this->channelDomainRepository = new StaticEntityRepository([
            new EntitySearchResult(1, new ChannelDomainCollection([$domain]), null, new Criteria(), $context->getContext()),
        ]);
        $dispatched = null;
        $this->eventDispatcher->addListener(
            MemberDoubleOptInRegistrationEvent::class,
            static function (MemberDoubleOptInRegistrationEvent $event) use (&$dispatched): void {
                $dispatched = $event;
            },
        );

        $this->createService(['core.loginRegistration.doubleOptInResendInterval' => 24])
            ->resendDoubleOptInMail($member, $context);

        static::assertInstanceOf(MemberDoubleOptInRegistrationEvent::class, $dispatched);
        static::assertStringStartsWith('https://fallback-domain.example.com', $dispatched->getConfirmUrl());
    }

    public function testMapMemberDoubleOptInDataReturnsUnchangedWhenDisabled(): void
    {
        $context = $this->createContext();
        $input = ['email' => 'test@example.com'];

        static::assertSame($input, $this->createService([
            'core.loginRegistration.doubleOptInRegistration' => false,
        ])->mapMemberDoubleOptInData($input, $context));
    }

    public function testMapMemberDoubleOptInDataSetsFieldsWhenEnabled(): void
    {
        $context = $this->createContext();
        $input = ['email' => 'test@example.com'];

        $result = $this->createService([
            'core.loginRegistration.doubleOptInRegistration' => true,
        ])->mapMemberDoubleOptInData($input, $context);

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
            $this->memberRepository,
            $this->eventDispatcher,
            new StaticSystemConfigService($systemConfig),
            $this->channelDomainRepository,
            new NativeClock(),
        );
    }

    private function createMemberEntity(string $hash, ?string $languageId = null): MemberEntity
    {
        $member = new MemberEntity();
        $member->setId(Uuid::randomHex());
        $member->setHash($hash);
        $member->setEmail('test@example.com');
        $member->setLanguageId($languageId ?? Uuid::randomHex());

        return $member;
    }

    /**
     * @param list<ChannelDomainEntity> $domains
     */
    private function createContext(?string $domainId = null, array $domains = []): ChannelContext
    {
        $channel = new ChannelEntity();
        $channel->setId(Uuid::randomHex());
        $channel->setDomains(new ChannelDomainCollection($domains));

        $group = new MemberGroupEntity();
        $group->setId(Uuid::randomHex());

        return Generator::generateChannelContext(
            token: 'token',
            domainId: $domainId,
            channel: $channel,
            currentMemberGroup: $group,
        );
    }

    private function createDomain(string $id, string $url, string $languageId): ChannelDomainEntity
    {
        $domain = new ChannelDomainEntity();
        $domain->setId($id);
        $domain->setUrl($url);
        $domain->setChannelId(Uuid::randomHex());
        $domain->setLanguageId($languageId);

        return $domain;
    }
}
