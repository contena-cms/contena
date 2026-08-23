<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Member\Event\MemberAccountRecoverRequestEvent;
use Contena\Core\System\Member\Event\PasswordRecoveryUrlEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Group('channel-api')]
class SendPasswordRecoveryMailRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<MemberCollection>
     */
    private EntityRepository $memberRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
        ]);
        $this->memberRepository = static::getContainer()->get('member.repository');
    }

    public function testResetUnknownEmail(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/recovery-password',
                [
                    'email' => 'unknown@example.com',
                    'frontendUrl' => 'http://localhost',
                ]
            );

        /** @var array<string, mixed> $response */
        $response = \json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('success', $response);
        static::assertTrue($response['success']);
    }

    public function testResetWithInvalidUrl(): void
    {
        $this->browser
            ->request(
                'POST',
                '/channel-api/account/recovery-password',
                [
                    'email' => 'unknown@example.com',
                    'frontendUrl' => 'http://invalid.example.com',
                ]
            );

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
        static::assertSame('VIOLATION::NO_SUCH_CHOICE_ERROR', $response['errors'][0]['code']);
    }

    public function testResetWithTryingToDisableValidation(): void
    {
        $this->createMember('member@example.com');

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/recovery-password?validateFrontendUrl=false',
                [
                    'email' => 'member@example.com',
                    'frontendUrl' => 'http://invalid.example.com',
                    'validateFrontendUrl' => false,
                ]
            );

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());

        $response = json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('VIOLATION::NO_SUCH_CHOICE_ERROR', $response['errors'][0]['code']);
    }

    public function testResetWithDisabledAccount(): void
    {
        $email = 'disabled@example.com';

        $this->createMember($email, false);

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/recovery-password?validateFrontendUrl=false',
                [
                    'email' => $email,
                    'frontendUrl' => 'http://localhost',
                    'validateFrontendUrl' => false,
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        /** @var array<string, mixed> $response */
        $response = \json_decode($this->browser->getResponse()->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertArrayHasKey('success', $response);
        static::assertTrue($response['success']);
    }

    /**
     * @param array{domain: string, expectDomain: string} $domainUrlTest
     */
    #[DataProvider('sendMailWithDomainAndLeadingSlashProvider')]
    public function testSendMailWithDomainAndLeadingSlash(array $domainUrlTest): void
    {
        $this->createMember('member@example.com');

        $this->addDomain($domainUrlTest['domain']);

        $caughtEvent = null;
        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
            MemberAccountRecoverRequestEvent::EVENT_NAME,
            static function (MemberAccountRecoverRequestEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/recovery-password',
                [
                    'email' => 'member@example.com',
                    'frontendUrl' => $domainUrlTest['expectDomain'],
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        static::assertInstanceOf(MemberAccountRecoverRequestEvent::class, $caughtEvent);
        static::assertStringStartsWith('http://custom.example.com/account/', $caughtEvent->getResetUrl());
    }

    public function testSendMailWithChangedUrl(): void
    {
        $this->createMember('member@example.com');

        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $systemConfigService->set('core.loginRegistration.pwdRecoverUrl', '/test/rec/password/%%RECOVERHASH%%"');

        /** @var EventDispatcherInterface $dispatcher */
        $dispatcher = static::getContainer()->get('event_dispatcher');

        $caughtEvent = null;
        $this->addEventListener(
            $dispatcher,
            MemberAccountRecoverRequestEvent::EVENT_NAME,
            static function (MemberAccountRecoverRequestEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->addEventListener(
            $dispatcher,
            PasswordRecoveryUrlEvent::class,
            static function (PasswordRecoveryUrlEvent $event): void {
                $event->setRecoveryUrl($event->getRecoveryUrl() . '/?somethingSpecial=1');
            }
        );

        $this->browser
            ->request(
                'POST',
                '/channel-api/account/recovery-password',
                [
                    'email' => 'member@example.com',
                    'frontendUrl' => 'http://localhost',
                ]
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), $this->browser->getResponse()->getContent() ?: '');

        static::assertInstanceOf(MemberAccountRecoverRequestEvent::class, $caughtEvent);
        static::assertStringStartsWith('http://localhost/test/rec/password/', $caughtEvent->getResetUrl());
        static::assertStringEndsWith('/?somethingSpecial=1', $caughtEvent->getResetUrl());
    }

    /**
     * @return iterable<string, array{array{domain: string, expectDomain: string}}>
     */
    public static function sendMailWithDomainAndLeadingSlashProvider(): iterable
    {
        yield 'domain without trailing slash is used unchanged' => [
            ['domain' => 'http://custom.example.com', 'expectDomain' => 'http://custom.example.com'],
        ];
        yield 'domain with trailing slash is normalized' => [
            ['domain' => 'http://custom.example.com/', 'expectDomain' => 'http://custom.example.com'],
        ];
        yield 'domain with double trailing slash is normalized' => [
            ['domain' => 'http://custom.example.com//', 'expectDomain' => 'http://custom.example.com'],
        ];
    }

    private function addDomain(string $url): void
    {
        static::getContainer()->get('channel_domain.repository')
            ->create([[
                'channelId' => $this->ids->get('channel'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'url' => $url,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
            ]], Context::createDefaultContext());
    }

    private function createMember(?string $email = null, bool $active = true): string
    {
        $memberId = Uuid::randomHex();

        $member = [
            'id' => $memberId,
            'active' => $active,
            'channelId' => $this->ids->get('channel'),
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Member',
            'memberNumber' => $memberId,
        ];

        $this->memberRepository->create([$member], Context::createDefaultContext());

        return $memberId;
    }
}
