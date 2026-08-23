<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\Member\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Member\Channel\RegisterRoute;
use Contena\Core\System\Member\Event\MemberConfirmRegisterUrlEvent;
use Contena\Core\System\Member\Event\MemberDoubleOptInRegistrationEvent;
use Contena\Core\System\Member\Event\MemberRegisterEvent;
use Contena\Core\System\Member\MemberCollection;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Rule\MemberLoggedInRule;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * @internal
 */
#[Group('channel-api')]
class RegisterRouteTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use IntegrationTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<MemberCollection>
     */
    private EntityRepository $memberRepository;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel'),
        ]);

        $this->memberRepository = static::getContainer()->get('member.repository');
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
    }

    public function testRegistration(): void
    {
        $this->register($this->browser);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $connection = static::getContainer()->get(Connection::class);
        $result = $connection->fetchOne(
            'SELECT `payload` FROM `channel_api_context` WHERE `member_id` = :memberId',
            ['memberId' => Uuid::fromHexToBytes($response['id'])]
        );
        $result = json_decode((string) $result, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('domainId', $result);
        static::assertSame('member', $response['apiAlias']);
        static::assertNotEmpty($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->login($this->browser);

        $contextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);
    }

    public function testRegisterEventWithMemberRules(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get('rule.repository')->create([[
            'id' => $ids->create('rule'),
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [
                ['type' => new MemberLoggedInRule()->getName(), 'value' => ['isLoggedIn' => true]],
            ],
        ]], Context::createDefaultContext());

        $ruleIds = null;
        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
            MemberRegisterEvent::class,
            static function (MemberRegisterEvent $event) use (&$ruleIds): void {
                $ruleIds = $event->getChannelContext()->getRuleIds();
            }
        );

        $this->register($this->browser);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('apiAlias', $response, \print_r($response, true));
        static::assertSame('member', $response['apiAlias']);

        $this->login($this->browser);

        $contextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        static::assertNotNull($ruleIds, 'Register event was not dispatched');
        static::assertContains($ids->get('rule'), $ruleIds, 'Context was not reloaded');
    }

    public function testRegistrationRejectsExistingEmailInSameChannel(): void
    {
        $this->createMember($this->ids->get('channel'), 'register@example.com');

        $this->register($this->browser);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertSame('VIOLATION::MEMBER_EMAIL_NOT_UNIQUE', $response['errors'][0]['code']);
    }

    public function testRegistrationAllowsExistingEmailInAnotherChannel(): void
    {
        $this->createMember($this->ids->get('channel'), 'register@example.com');

        $browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel-2'),
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => 'http://localhost2',
            ]],
        ]);

        $this->register($browser, 'http://localhost2');

        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(Response::HTTP_OK, $browser->getResponse()->getStatusCode());
        static::assertSame('member', $response['apiAlias']);
        static::assertArrayNotHasKey('errors', $response);
        static::assertNotEmpty($browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->login($browser);

        $contextToken = $browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);
    }

    public function testRegistrationWithGivenToken(): void
    {
        $this->register($this->browser);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('member', $response['apiAlias']);
        static::assertNotEmpty($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        $this->browser->request('GET', '/channel-api/account/member');

        $member = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('errors', $member);
        static::assertSame('member', $member['apiAlias']);
    }

    /**
     * @param array<string, string> $domainUrlTest
     */
    #[DataProvider('registerWithDomainAndLeadingSlashProvider')]
    public function testRegistrationWithTrailingSlashUrl(array $domainUrlTest): void
    {
        $browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('channel-3'),
            'domains' => [[
                'languageId' => Defaults::LANGUAGE_SYSTEM,
                'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                'url' => $domainUrlTest['domain'],
            ]],
        ]);

        $this->register($browser, $domainUrlTest['expectDomain']);

        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertSame('member', $response['apiAlias']);
        static::assertArrayNotHasKey('errors', $response);
        static::assertNotEmpty($browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->login($browser);

        $contextToken = $browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);
    }

    /**
     * @return iterable<string, array{array{domain: string, expectDomain: string}}>
     */
    public static function registerWithDomainAndLeadingSlashProvider(): iterable
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

    public function testDoubleOptIn(): void
    {
        $this->systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);

        $this->register($this->browser);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('member', $response['apiAlias']);

        $memberId = $response['id'];

        $this->login($this->browser);

        $response = $this->browser->getResponse();
        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);

        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $responseData);
        static::assertSame('SYSTEM__MEMBER_OPTIN_NOT_COMPLETED', $responseData['errors'][0]['code']);
        static::assertSame('401', $responseData['errors'][0]['status']);

        $member = $this->fetchMember($memberId);
        $this->confirm($this->browser, $member);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->login($this->browser);

        $contextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotEmpty($contextToken);
    }

    public function testDoubleOptInContextReloadForEvents(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get('rule.repository')->create([[
            'id' => $ids->create('rule'),
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [
                ['type' => new MemberLoggedInRule()->getName(), 'value' => ['isLoggedIn' => true]],
            ],
        ]], Context::createDefaultContext());

        $ruleIds = null;
        $this->addEventListener(
            static::getContainer()->get('event_dispatcher'),
            MemberRegisterEvent::class,
            static function (MemberRegisterEvent $event) use (&$ruleIds): void {
                $ruleIds = $event->getChannelContext()->getRuleIds();
            }
        );

        $this->systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);

        $this->register($this->browser);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('member', $response['apiAlias']);

        $this->confirm($this->browser, $this->fetchMember($response['id']));

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertNotNull($ruleIds, 'Register event was not dispatched');
        static::assertContains($ids->get('rule'), $ruleIds, 'Context was not reloaded');
    }

    public function testDoubleOptInChangedUrl(): void
    {
        $this->systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);
        $this->systemConfigService->set('core.loginRegistration.confirmationUrl', '/confirm/custom/%%HASHEDEMAIL%%/%%SUBSCRIBEHASH%%');

        $dispatcher = static::getContainer()->get('event_dispatcher');

        $this->addEventListener(
            $dispatcher,
            MemberConfirmRegisterUrlEvent::class,
            static function (MemberConfirmRegisterUrlEvent $event): void {
                $event->setConfirmUrl($event->getConfirmUrl());
            }
        );

        $caughtEvent = null;
        $this->addEventListener(
            $dispatcher,
            MemberDoubleOptInRegistrationEvent::class,
            static function (MemberDoubleOptInRegistrationEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->register($this->browser);

        static::assertInstanceOf(MemberDoubleOptInRegistrationEvent::class, $caughtEvent);
        static::assertStringStartsWith('http://localhost/confirm/custom/', $caughtEvent->getConfirmUrl());
    }

    public function testDoubleOptInGivenTokenIsNotLoggedIn(): void
    {
        $this->systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);

        $this->register($this->browser);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('member', $response['apiAlias']);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', (string) $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        $this->browser->request('GET', '/channel-api/account/member');

        $member = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $member);
        static::assertSame(RoutingException::CHANNEL_MEMBER_NOT_LOGGED_IN, $member['errors'][0]['code']);
    }

    public function testDoubleOptInWithHeaderToken(): void
    {
        $this->systemConfigService->set('core.loginRegistration.doubleOptInRegistration', true);

        $this->register($this->browser);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('member', $response['apiAlias']);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', (string) $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        $this->browser->request('GET', '/channel-api/account/member');

        $member = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $member);
        static::assertSame(RoutingException::CHANNEL_MEMBER_NOT_LOGGED_IN, $member['errors'][0]['code']);

        $this->confirm($this->browser, $this->fetchMember($response['id']));

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($response['active']);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', (string) $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        $this->browser->request('GET', '/channel-api/account/member');

        $member = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('errors', $member);
        static::assertSame('member', $response['apiAlias']);
    }

    public function testRegistrationWithRequestedGroup(): void
    {
        static::getContainer()->get('member_group.repository')->create([[
            'id' => $this->ids->create('group'),
            'name' => 'Content team',
            'registrationActive' => true,
            'registrationTitle' => 'Content team registration',
            'registrationChannels' => [['id' => $this->getChannelApiChannelId()]],
        ]], Context::createDefaultContext());

        $this->register($this->browser, overrides: ['requestedGroupId' => $this->ids->get('group')]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('member', $response['apiAlias']);

        $member = $this->fetchMember($response['id']);
        static::assertSame($this->ids->get('group'), $member->getRequestedGroupId());
    }

    public function testContextChangedBetweenRegistration(): void
    {
        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create('test', $this->getChannelApiChannelId());

        $bag = new RequestDataBag($this->getRegistrationData());
        static::getContainer()->get(RegisterRoute::class)->register($bag, $context);

        static::assertNotSame('test', $context->getToken());
    }

    public function testRegistrationWithIdnEmail(): void
    {
        $this->register($this->browser, overrides: ['email' => 'register@exämple.com']);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $email = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT email FROM member WHERE id = UNHEX(:memberId)',
            ['memberId' => $response['id']]
        );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('register@xn--exmple-cua.com', $email);
    }

    public function testRegisterWithTooLongPassword(): void
    {
        $this->register($this->browser, overrides: [
            'password' => \str_repeat('a', PasswordHasherInterface::MAX_PASSWORD_LENGTH + 1),
        ]);

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);

        $error = $response['errors'][0];
        static::assertSame('VIOLATION::TOO_LONG_ERROR', $error['code']);
        static::assertSame('/password', $error['source']['pointer']);
        static::assertSame(':PASSWORD_IS_TOO_LONG', $error['detail']);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function register(KernelBrowser $browser, string $frontendUrl = 'http://localhost', array $overrides = []): void
    {
        $browser->request(
            'POST',
            '/channel-api/account/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([...$this->getRegistrationData($frontendUrl), ...$overrides], \JSON_THROW_ON_ERROR)
        );
    }

    private function login(KernelBrowser $browser): void
    {
        $browser->request(
            'POST',
            '/channel-api/account/login',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'email' => 'register@example.com',
                'password' => '12345678',
            ], \JSON_THROW_ON_ERROR)
        );
    }

    private function confirm(KernelBrowser $browser, MemberEntity $member): void
    {
        $browser->request(
            'POST',
            '/channel-api/account/register-confirm',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'hash' => $member->getHash(),
                'em' => Hasher::hash('register@example.com', 'sha1'),
            ], \JSON_THROW_ON_ERROR)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getRegistrationData(string $frontendUrl = 'http://localhost'): array
    {
        return [
            'name' => 'Max Member',
            'phoneNumber' => '123456789',
            'password' => '12345678',
            'email' => 'register@example.com',
            'title' => 'PhD',
            'active' => true,
            'birthdayYear' => 2000,
            'birthdayMonth' => 1,
            'birthdayDay' => 22,
            'frontendUrl' => $frontendUrl,
        ];
    }

    private function createMember(string $channelId, string $email): string
    {
        $memberId = Uuid::randomHex();

        $this->memberRepository->create([[
            'id' => $memberId,
            'channelId' => $channelId,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'groupId' => TestDefaults::FALLBACK_MEMBER_GROUP,
            'email' => $email,
            'password' => TestDefaults::HASHED_PASSWORD,
            'name' => 'Max Member',
            'memberNumber' => $memberId,
            'active' => true,
        ]], Context::createDefaultContext());

        return $memberId;
    }

    private function fetchMember(string $memberId): MemberEntity
    {
        $member = $this->memberRepository
            ->search(new Criteria([$memberId]), Context::createDefaultContext())
            ->getEntities()
            ->first();
        static::assertInstanceOf(MemberEntity::class, $member);

        return $member;
    }
}
