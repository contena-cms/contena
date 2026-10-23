<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\System\User\Channel;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\User\UserCollection;
use Contena\Core\System\User\UserDefinition;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Event\UserConfirmRegisterUrlEvent;
use Contena\Core\System\User\Event\UserDoubleOptInRegistrationEvent;
use Contena\Core\System\User\Event\UserRegisterEvent;
use Contena\Core\System\User\Rule\UserLoggedInRule;
use Contena\Core\System\User\Rule\IsNewsletterRecipientRule;
use Contena\Core\System\User\Channel\RegisterRoute;
use Contena\Core\Content\Newsletter\Channel\NewsletterSubscribeRoute;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Test\TestCaseBase\CountryAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainCollection;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Salutation\SalutationDefinition;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;

/**
 * @internal
 */
#[Group('store-api')]
class RegisterRouteTest extends TestCase
{
    use CountryAddToChannelTestBehaviour;
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    /**
     * @var EntityRepository<UserCollection>
     */
    private EntityRepository $userRepository;

    private SystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);

        $this->addCountriesToChannel([], $this->ids->get('sales-channel'));

        $this->assignChannelContext($this->browser);
        $this->userRepository = static::getContainer()->get('user.repository');

        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);
    }

    public function testRegistration(): void
    {
        $registrationData = $this->getRegistrationData();
        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $connection = static::getContainer()->get(Connection::class);
        $result = $connection->fetchOne(
            'SELECT `payload` FROM `channel_api_context` WHERE `user_id` = :userId ',
            [
                'userId' => Uuid::fromHexToBytes($response['id']),
            ]
        );
        $result = json_decode((string) $result, true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('domainId', $result);

        static::assertSame('user', $response['apiAlias']);
        static::assertIsString($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ], \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    public function testRegisterEventWithUserRules(): void
    {
        $ids = new IdsCollection();

        $rule = [
            'id' => $ids->create('rule'),
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [
                ['type' => (new UserLoggedInRule())->getName(), 'value' => ['isLoggedIn' => true]],
            ],
        ];

        static::getContainer()->get('rule.repository')->create([$rule], Context::createDefaultContext());

        $ruleIds = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(UserRegisterEvent::class, static function (UserRegisterEvent $event) use (&$ruleIds): void {
            $ruleIds = $event->getChannelContext()->getRuleIds();
        });

        $this->browser->request('POST', '/store-api/account/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($this->getRegistrationData(), \JSON_THROW_ON_ERROR));

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('apiAlias', $response, \print_r($response, true));
        static::assertSame('user', $response['apiAlias']);

        $this->browser->request('POST', '/store-api/account/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['email' => 'teg-reg@example.com', 'password' => '12345678'], \JSON_THROW_ON_ERROR));

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);

        static::assertNotNull($ruleIds, 'Register event was not dispatched');
        static::assertContains($ids->get('rule'), $ruleIds, 'Context was not reloaded');
    }

    public function testRegisterEventWithNewsletterRecipientRule(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get('newsletter_recipient.repository')->create([
            [
                'id' => $ids->create('newsletter-recipient'),
                'email' => 'teg-reg@example.com',
                'channelId' => $this->ids->get('sales-channel'),
                'status' => NewsletterSubscribeRoute::STATUS_DIRECT,
                'hash' => Uuid::randomHex(),
            ],
        ], Context::createDefaultContext());

        $rule = [
            'id' => $ids->create('rule'),
            'name' => 'Test newsletter recipient rule',
            'priority' => 1,
            'conditions' => [
                ['type' => (new IsNewsletterRecipientRule())->getName(), 'value' => ['isNewsletterRecipient' => true]],
            ],
        ];

        static::getContainer()->get('rule.repository')->create([$rule], Context::createDefaultContext());

        $ruleIds = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(UserRegisterEvent::class, static function (UserRegisterEvent $event) use (&$ruleIds): void {
            $ruleIds = $event->getChannelContext()->getRuleIds();
        });

        $this->browser->request('POST', '/store-api/account/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($this->getRegistrationData(), \JSON_THROW_ON_ERROR));

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('apiAlias', $response, \print_r($response, true));
        static::assertSame('user', $response['apiAlias']);
        static::assertNotNull($ruleIds, 'Register event was not dispatched');
        static::assertContains($ids->get('rule'), $ruleIds, 'Newsletter recipient rule was not available in the refreshed context');
    }

    #[DataProvider('userBoundToChannelProvider')]
    public function testRegistrationWithUserScope(
        bool $isUserScoped,
        bool $hasGlobalAccount,
        bool $hasBoundAccount,
        bool $requestOnSameChannel,
        int $expectedStatus
    ): void {
        static::getContainer()->get(SystemConfigService::class)->set('core.systemWideLoginRegistration.isUserBoundToChannel', $isUserScoped);

        if ($hasGlobalAccount || $hasBoundAccount) {
            $boundChannel = $isUserScoped && $hasBoundAccount;
            $this->createBoundUser($this->ids->get('sales-channel'), $this->getRegistrationData()['email'], $boundChannel);
        }

        $browser = $requestOnSameChannel ? $this->browser : $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel-2'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost2',
                ],
            ],
        ]);

        $frontendUrl = $requestOnSameChannel ? 'http://localhost' : 'http://localhost2';

        $browser->request(
            'POST',
            '/store-api/account/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->getRegistrationData($frontendUrl), \JSON_THROW_ON_ERROR)
        );

        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($expectedStatus, $browser->getResponse()->getStatusCode());

        if ($expectedStatus === 200) {
            static::assertSame('user', $response['apiAlias']);
            static::assertArrayNotHasKey('errors', $response);
            static::assertIsString($browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
            static::assertNotSame('', $browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

            $browser->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ], \JSON_THROW_ON_ERROR)
            );

            $response = $browser->getResponse();

            $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
            static::assertNotSame('', $contextToken);
        } else {
            static::assertNotCount(0, $response['errors']);
            static::assertSame('VIOLATION::CUSTOMER_EMAIL_NOT_UNIQUE', $response['errors'][0]['code']);
        }
    }

    public function testRegistrationWithGivenToken(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($this->getRegistrationData(), \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('user', $response['apiAlias']);
        $contextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertIsString($contextToken);
        static::assertNotSame('', $contextToken);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', $contextToken);

        $this->browser
            ->request(
                'GET',
                '/store-api/account/user'
            );

        $user = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('errors', $user);
        static::assertSame('user', $user['apiAlias']);
    }

    /**
     * @param array<string, string> $domainUrlTest
     */
    #[DataProvider('registerWithDomainAndLeadingSlashProvider')]
    public function testRegistrationWithTrailingSlashUrl(array $domainUrlTest): void
    {
        $browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel-3'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => $domainUrlTest['domain'],
                ],
            ],
        ]);

        $this->systemConfigService->set(
            'core.loginRegistration.doubleOptInRegistration',
            true,
            $this->ids->get('sales-channel-3')
        );

        $browser->request(
            'POST',
            '/store-api/account/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->getRegistrationData($domainUrlTest['expectDomain']), \JSON_THROW_ON_ERROR)
        );

        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        static::assertSame('user', $response['apiAlias']);
        static::assertArrayNotHasKey('errors', $response);
    }

    /**
     * @return iterable<string, array{array{domain: string, expectDomain: string}}>
     */
    public static function registerWithDomainAndLeadingSlashProvider(): iterable
    {
        yield 'domain without trailing slash is used unchanged' => [
            ['domain' => 'http://my-evil-page', 'expectDomain' => 'http://my-evil-page'],
        ];
        yield 'domain with trailing slash is normalized' => [
            ['domain' => 'http://my-evil-page/', 'expectDomain' => 'http://my-evil-page'],
        ];
        yield 'domain with double trailing slash is normalized' => [
            ['domain' => 'http://my-evil-page//', 'expectDomain' => 'http://my-evil-page'],
        ];
    }

    public function testRegistrationOnChannelWithoutDomains(): void
    {
        $browser = $this->createChannelBrowserWithoutDomains();

        $browser->request(
            'POST',
            '/store-api/account/register',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($this->getRegistrationData('https://headless.example.com'), \JSON_THROW_ON_ERROR)
        );

        $response = json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());
        static::assertArrayNotHasKey('errors', $response);
        static::assertSame('user', $response['apiAlias']);
        static::assertIsString($browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('', $browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testRegistrationAcceptsForeignFrontendUrlWithoutDoubleOptIn(): void
    {
        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($this->getRegistrationData('http://my-evil-page'), \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        static::assertArrayNotHasKey('errors', $response);
        static::assertSame('user', $response['apiAlias']);
    }

    public function testRegistrationRejectsForeignFrontendUrlWithDoubleOptIn(): void
    {
        $this->systemConfigService->set(
            'core.loginRegistration.doubleOptInRegistration',
            true,
            $this->ids->get('sales-channel')
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($this->getRegistrationData('http://my-evil-page'), \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertSame('VIOLATION::NO_SUCH_CHOICE_ERROR', $response['errors'][0]['code']);
        static::assertSame('/frontendUrl', $response['errors'][0]['source']['pointer']);
    }

    public function testDoubleOptin(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.loginRegistration.doubleOptInRegistration', true);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($this->getRegistrationData(), \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('user', $response['apiAlias']);

        $userId = $response['id'];

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ], \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();

        if (Feature::isActive('CACHE_REWORK')) {
            static::assertNull($response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        } else {
            static::assertSame(
                $this->browser->getRequest()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN),
                $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN)
            );
        }

        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $responseData);
        static::assertSame('CHECKOUT__CUSTOMER_OPTIN_NOT_COMPLETED', $responseData['errors'][0]['code']);
        static::assertSame('401', $responseData['errors'][0]['status']);

        $criteria = new Criteria([$userId]);
        $user = $this->userRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register-confirm',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'hash' => $user->getHash(),
                    'em' => Hasher::hash('teg-reg@example.com', 'sha1'),
                ], \JSON_THROW_ON_ERROR)
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ], \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    public function testDoubleOptinContextReloadForEvents(): void
    {
        $ids = new IdsCollection();

        $rule = [
            'id' => $ids->create('rule'),
            'name' => 'Test rule',
            'priority' => 1,
            'conditions' => [
                ['type' => (new UserLoggedInRule())->getName(), 'value' => ['isLoggedIn' => true]],
            ],
        ];

        static::getContainer()->get('rule.repository')->create([$rule], Context::createDefaultContext());

        $ruleIds = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(UserRegisterEvent::class, static function (UserRegisterEvent $event) use (&$ruleIds): void {
            $ruleIds = $event->getChannelContext()->getRuleIds();
        });

        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.loginRegistration.doubleOptInRegistration', true);

        $this->browser->request('POST', '/store-api/account/register', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($this->getRegistrationData(), \JSON_THROW_ON_ERROR));

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('user', $response['apiAlias']);

        $userId = $response['id'];

        $criteria = new Criteria([$userId]);
        $user = $this->userRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);

        $this->browser->request('POST', '/store-api/account/register-confirm', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['hash' => $user->getHash(), 'em' => Hasher::hash('teg-reg@example.com', 'sha1')], \JSON_THROW_ON_ERROR));

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        static::assertNotNull($ruleIds, 'Register event was not dispatched');
        static::assertContains($ids->get('rule'), $ruleIds, 'Context was not reloaded');
    }

    public function testDoubleOptinChangedUrl(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.loginRegistration.doubleOptInRegistration', true);
        $systemConfig->set('core.loginRegistration.confirmationUrl', '/confirm/custom/%%HASHEDEMAIL%%/%%SUBSCRIBEHASH%%');

        EventHookDispatcher::fromContainer(static::getContainer())->on(
            UserConfirmRegisterUrlEvent::class,
            static function (UserConfirmRegisterUrlEvent $event): void {
                $event->setConfirmUrl($event->getConfirmUrl());
            }
        );

        $caughtEvent = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            UserDoubleOptInRegistrationEvent::class,
            static function (UserDoubleOptInRegistrationEvent $event) use (&$caughtEvent): void {
                $caughtEvent = $event;
            }
        );

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($this->getRegistrationData(), \JSON_THROW_ON_ERROR)
            );

        static::assertInstanceOf(UserDoubleOptInRegistrationEvent::class, $caughtEvent);
        static::assertStringStartsWith('http://localhost/confirm/custom/', $caughtEvent->getConfirmUrl());
    }

    public function testDoubleOptinGivenTokenIsNotLoggedin(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.loginRegistration.doubleOptInRegistration', true);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($this->getRegistrationData(), \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('user', $response['apiAlias']);

        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', (string) $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'GET',
                '/store-api/account/user'
            );

        $user = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $user);
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $user['errors'][0]['code']);
    }

    public function testDoubleOptinWithHeaderToken(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);

        $systemConfig->set('core.loginRegistration.doubleOptInRegistration', true);

        // Register
        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($this->getRegistrationData(), \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('user', $response['apiAlias']);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', (string) $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        // Validate I am not logged in
        $this->browser
            ->request(
                'GET',
                '/store-api/account/user'
            );

        $user = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $user);
        static::assertSame(RoutingException::CUSTOMER_NOT_LOGGED_IN_CODE, $user['errors'][0]['code']);

        $userId = $response['id'];

        $criteria = new Criteria([$userId]);
        $user = $this->userRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register-confirm',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'hash' => $user->getHash(),
                    'em' => Hasher::hash('teg-reg@example.com', 'sha1'),
                ], \JSON_THROW_ON_ERROR)
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($response['active']);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', (string) $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'GET',
                '/store-api/account/user'
            );

        $user = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayNotHasKey('errors', $user);
        static::assertSame('user', $response['apiAlias']);
    }

    public function testRegistrationWithRequestedGroup(): void
    {
        $userGroupRepository = static::getContainer()->get('user_group.repository');
        $userGroupRepository->create([
            [
                'id' => $this->ids->create('group'),
                'name' => 'foo',
                'registration' => [
                    'title' => 'test',
                ],
                'registrationChannels' => [['id' => $this->getChannelApiChannelId()]],
            ],
        ], Context::createDefaultContext());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([...$this->getRegistrationData(), ...['requestedGroupId' => $this->ids->get('group')]], \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('user', $response['apiAlias']);

        $user = $this->userRepository->search(new Criteria([$response['id']]), Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(UserEntity::class, $user);

        static::assertSame($this->ids->get('group'), $user->getRequestedGroupId());
    }

    public function testContextChangedBetweenRegistration(): void
    {
        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create('test', $this->getChannelApiChannelId());

        $bag = new RequestDataBag($this->getRegistrationData());
        static::getContainer()->get(RegisterRoute::class)->register($bag, $context);

        static::assertNotSame('test', $context->getToken());
    }

    /**
     * @return iterable<string, array{isUserScoped: bool, hasGlobalAccount: bool, hasBoundAccount: bool, requestOnSameChannel:bool, expectedStatus: int}>
     */
    public static function userBoundToChannelProvider(): iterable
    {
        yield 'scoped user cannot reuse a bound account on the same sales channel' => [
            'isUserScoped' => true,
            'hasGlobalAccount' => false,
            'hasBoundAccount' => true,
            'requestOnSameChannel' => true,
            'expectedStatus' => 400,
        ];
        yield 'scoped user can reuse a bound account on another sales channel' => [
            'isUserScoped' => true,
            'hasGlobalAccount' => false,
            'hasBoundAccount' => true,
            'requestOnSameChannel' => false,
            'expectedStatus' => 200,
        ];
        yield 'scoped user cannot reuse a global account on the same sales channel' => [
            'isUserScoped' => true,
            'hasGlobalAccount' => true,
            'hasBoundAccount' => false,
            'requestOnSameChannel' => true,
            'expectedStatus' => 400,
        ];
        yield 'scoped user cannot reuse a global account on another sales channel' => [
            'isUserScoped' => true,
            'hasGlobalAccount' => true,
            'hasBoundAccount' => false,
            'requestOnSameChannel' => false,
            'expectedStatus' => 400,
        ];
        yield 'scoped user can register without an existing account' => [
            'isUserScoped' => true,
            'hasGlobalAccount' => false,
            'hasBoundAccount' => false,
            'requestOnSameChannel' => true,
            'expectedStatus' => 200,
        ];
        yield 'unscoped user cannot reuse a bound account on the same sales channel' => [
            'isUserScoped' => false,
            'hasGlobalAccount' => false,
            'hasBoundAccount' => true,
            'requestOnSameChannel' => true,
            'expectedStatus' => 400,
        ];
        yield 'unscoped user cannot reuse a bound account on another sales channel' => [
            'isUserScoped' => false,
            'hasGlobalAccount' => false,
            'hasBoundAccount' => true,
            'requestOnSameChannel' => false,
            'expectedStatus' => 400,
        ];
        yield 'unscoped user cannot reuse a global account on the same sales channel' => [
            'isUserScoped' => false,
            'hasGlobalAccount' => true,
            'hasBoundAccount' => false,
            'requestOnSameChannel' => true,
            'expectedStatus' => 400,
        ];
        yield 'unscoped user cannot reuse a global account on another sales channel' => [
            'isUserScoped' => false,
            'hasGlobalAccount' => true,
            'hasBoundAccount' => false,
            'requestOnSameChannel' => false,
            'expectedStatus' => 400,
        ];
        yield 'unscoped user can register without an existing account' => [
            'isUserScoped' => false,
            'hasGlobalAccount' => false,
            'hasBoundAccount' => false,
            'requestOnSameChannel' => true,
            'expectedStatus' => 200,
        ];
    }

    public function testRegistrationWithAllowedAccountType(): void
    {
        $accountTypes = static::getContainer()->getParameter('user.account_types');
        static::assertIsArray($accountTypes);
        static::assertNotCount(0, $accountTypes);
        $accountType = $accountTypes[array_rand($accountTypes)];

        $additionalData = [
            'accountType' => $accountType,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => [
                'DE123456789',
            ],
        ];
        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame($accountType, $response['accountType']);
        static::assertIsString($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ], \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    public function testRegistrationWithWrongAccountType(): void
    {
        $accountTypes = static::getContainer()->getParameter('user.account_types');
        static::assertIsArray($accountTypes);
        $notAllowedAccountType = implode('', $accountTypes);
        $additionalData = [
            'accountType' => $notAllowedAccountType,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => [
                'DE123456789',
            ],
        ];

        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);
        static::assertCount(1, $response['errors']);
        static::assertIsArray($response['errors'][0]);
        static::assertSame('VIOLATION::NO_SUCH_CHOICE_ERROR', $response['errors'][0]['code']);
    }

    public function testRegistrationRequiresBirthdayWhenConfigured(): void
    {
        $this->systemConfigService->set('core.loginRegistration.showBirthdayField', true);
        $this->systemConfigService->set('core.loginRegistration.birthdayFieldRequired', true);

        $registrationData = $this->getRegistrationData();
        unset($registrationData['birthdayDay'], $registrationData['birthdayMonth'], $registrationData['birthdayYear']);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        $codesByPointer = array_column(
            array_map(static fn (array $error): array => ['pointer' => $error['source']['pointer'], 'code' => $error['code']], $response['errors']),
            'code',
            'pointer'
        );

        static::assertSame('VIOLATION::IS_BLANK_ERROR', $codesByPointer['/birthdayDay'] ?? null);
        static::assertSame('VIOLATION::IS_BLANK_ERROR', $codesByPointer['/birthdayMonth'] ?? null);
        static::assertSame('VIOLATION::IS_BLANK_ERROR', $codesByPointer['/birthdayYear'] ?? null);
    }

    public function testRegistrationWithoutAccountTypeIsEmptyString(): void
    {
        $additionalData = [
            'accountType' => '',
        ];
        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $userDefinition = new UserDefinition();
        static::assertArrayHasKey('accountType', $userDefinition->getDefaults());
        static::assertSame($userDefinition->getDefaults()['accountType'], $response['accountType']);

        static::assertIsString($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ], \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    public function testRegistrationWithoutAccountTypeFallbackToDefaultValue(): void
    {
        $registrationData = array_merge_recursive($this->getRegistrationData());

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $userDefinition = new UserDefinition();
        static::assertArrayHasKey('accountType', $userDefinition->getDefaults());
        static::assertSame($userDefinition->getDefaults()['accountType'], $response['accountType']);

        static::assertIsString($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ], \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    public function testRegistrationCommercialAccountWithVatIds(): void
    {
        $additionalData = [
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => [
                'DE123456789',
            ],
        ];
        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('user', $response['apiAlias']);
        static::assertSame(['DE123456789'], $response['vatIds']);
        static::assertIsString($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ], \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    public function testRegistrationCommercialAccountWithVatIdsIsEmpty(): void
    {
        $additionalData = [
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => [],
        ];
        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        if (static::getContainer()->get(SystemConfigService::class)->get('core.loginRegistration.vatIdFieldRequired', $this->getChannelApiChannelId())) {
            static::assertArrayHasKey('errors', $response);
        } else {
            static::assertSame('user', $response['apiAlias']);
            static::assertCount(0, $response['vatIds']);
            static::assertIsString($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
            static::assertNotSame('', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

            $this->browser
                ->request(
                    'POST',
                    '/store-api/account/login',
                    [],
                    [],
                    ['CONTENT_TYPE' => 'application/json'],
                    json_encode([
                        'email' => 'teg-reg@example.com',
                        'password' => '12345678',
                    ], \JSON_THROW_ON_ERROR)
                );

            $response = $this->browser->getResponse();

            $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
            static::assertNotSame('', $contextToken);
        }
    }

    public function testRegistrationBusinessAccountWithCountryVatIdsRequired(): void
    {
        $billingAddressCountryId = $this->getCountryIdByIsoCode('SE');
        $shippingAddressCountryId = $this->getCountryIdByIsoCode('SI');

        $this->addCountriesToChannel([$billingAddressCountryId, $shippingAddressCountryId], $this->ids->get('sales-channel'));

        static::getContainer()->get(Connection::class)
            ->executeStatement(
                'UPDATE `country` SET `check_vat_id_pattern` = 1 WHERE id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList([$billingAddressCountryId, $shippingAddressCountryId])],
                ['ids' => ArrayParameterType::BINARY]
            );

        $additionalData = [
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company',
                'countryId' => $billingAddressCountryId,
            ],
            'shippingAddress' => [
                'countryId' => $shippingAddressCountryId,
            ],
            'vatIds' => [
                'SE111111111111',
            ],
        ];

        $registrationData = array_replace_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
    }

    public function testRegistrationBusinessAccountWithVatIdsNotMatchRegex(): void
    {
        static::getContainer()->get(Connection::class)
            ->executeStatement('UPDATE `country` SET `check_vat_id_pattern` = 1, `vat_id_pattern` = "(DE)?[0-9]{9}" WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->getValidCountryId($this->ids->get('sales-channel')))]);

        $additionalData = [
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => [
                'abcd',
            ],
        ];

        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testRegistrationBusinessAccountWithVatIdsMatchRegex(): void
    {
        static::getContainer()->get(Connection::class)
            ->executeStatement('UPDATE `country` SET `check_vat_id_pattern` = 1, `vat_id_pattern` = "(DE)?[0-9]{9}" WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->getValidCountryId())]);

        $additionalData = [
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => [
                '123456789',
            ],
        ];

        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('user', $response['apiAlias']);
        static::assertIsString($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ], \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    public function testRegistrationBusinessAccountWithVatIdsIsNormalizedBeforeMatchingRegex(): void
    {
        static::getContainer()->get(Connection::class)
            ->executeStatement('UPDATE `country` SET `check_vat_id_pattern` = 1, `vat_id_pattern` = "(DE)?[0-9]{9}" WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->getValidCountryId($this->ids->get('sales-channel')))]);

        $additionalData = [
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => [
                'de 123456789',
            ],
        ];

        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('user', $response['apiAlias']);
        static::assertSame(['DE123456789'], $response['vatIds']);
    }

    public function testRegistrationBusinessAccountWithScalarVatIdsReturnsValidationError(): void
    {
        static::getContainer()->get(Connection::class)
            ->executeStatement('UPDATE `country` SET `check_vat_id_pattern` = 1, `vat_id_pattern` = "(DE)?[0-9]{9}" WHERE id = :id', ['id' => Uuid::fromHexToBytes($this->getValidCountryId($this->ids->get('sales-channel')))]);

        $additionalData = [
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company',
                'department' => 'Test Department',
            ],
            'vatIds' => 'abc',
        ];

        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testRegistrationCommercialAccountWithDifferentCommercialAddress(): void
    {
        $this->systemConfigService->set('core.loginRegistration.showAccountTypeSelection', true);

        $additionalData = [
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'company' => 'Test Company 1',
                'department' => 'Test Department 1',
            ],
            'shippingAddress' => [
                'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
                'company' => 'Test Company 2',
                'department' => 'Test Department 2',
            ],
        ];
        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('user', $response['apiAlias']);

        $criteria = new Criteria([$response['id']]);
        $criteria->addAssociation('addresses');

        $user = $this->userRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(UserEntity::class, $user);

        $userAddressCollection = $user->getAddresses();

        static::assertNotNull($userAddressCollection);

        static::assertCount(2, $userAddressCollection->getElements());

        $addressesCompany = [];
        $addressesDepartment = [];
        foreach ($userAddressCollection->getElements() as $address) {
            $addressesCompany[] = $address->getCompany();
            $addressesDepartment[] = $address->getDepartment();
        }

        sort($addressesCompany);
        sort($addressesDepartment);

        static::assertSame('Test Company 1', $addressesCompany[0]);
        static::assertSame('Test Company 2', $addressesCompany[1]);
        static::assertSame('Test Department 1', $addressesDepartment[0]);
        static::assertSame('Test Department 2', $addressesDepartment[1]);

        static::assertIsString($this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
        static::assertNotSame('', $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));

        $this->browser
            ->request(
                'POST',
                '/store-api/account/login',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'email' => 'teg-reg@example.com',
                    'password' => '12345678',
                ], \JSON_THROW_ON_ERROR)
            );

        $response = $this->browser->getResponse();

        $contextToken = $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN) ?? '';
        static::assertNotSame('', $contextToken);
    }

    public function testRegistrationCommercialAccountWithDifferentCommercialAddressButEmptyCompany(): void
    {
        $this->systemConfigService->set('core.loginRegistration.showAccountTypeSelection', true);

        $additionalData = [
            'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
            'billingAddress' => [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'company' => 'Test Company 1',
                'department' => 'Test Department 1',
            ],
            'shippingAddress' => [
                'accountType' => UserEntity::ACCOUNT_TYPE_BUSINESS,
                'department' => 'Test Department 2',
            ],
        ];
        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertArrayHasKey('errors', $response);
    }

    public function testRegistrationWithActiveCart(): void
    {
        $this->createBlogTestData();
        $this->browser
            ->request(
                'POST',
                '/store-api/checkout/cart/line-item',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode([
                    'items' => [
                        [
                            'id' => $this->ids->get('p1'),
                            'label' => 'foo',
                            'type' => 'blog',
                            'referencedId' => $this->ids->get('p1'),
                        ],
                    ],
                ], \JSON_THROW_ON_ERROR)
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($this->browser->getResponse()->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        $contextToken = $this->browser->getResponse()->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertNotNull($contextToken);
        $this->browser->setServerParameter('HTTP_SW_CONTEXT_TOKEN', (string) $contextToken);

        $additionalData = [
            'guest' => true,
        ];

        $registrationData = array_merge_recursive($this->getRegistrationData(), $additionalData);
        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertTrue($this->browser->getResponse()->headers->has(PlatformRequest::HEADER_CONTEXT_TOKEN));
        $newContextToken = $this->browser->getResponse()->headers->all(PlatformRequest::HEADER_CONTEXT_TOKEN);
        static::assertCount(1, $newContextToken);
        static::assertNotSame($contextToken, $newContextToken);
    }

    public function testRegistrationWithEmptyBillingAddress(): void
    {
        $registrationData = $this->getRegistrationData();
        unset($registrationData['billingAddress']);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
    }

    public function testRegistrationWithoutAnyAddress(): void
    {
        $registrationData = $this->getRegistrationData();
        unset($registrationData['billingAddress']);
        unset($registrationData['shippingAddress']);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotCount(0, $response['errors']);
        static::assertSame('VIOLATION::IS_BLANK_ERROR', $response['errors'][0]['code']);
    }

    public function testRegistrationWithNonArrayBillingAddressAndWithEmptyShippingAddress(): void
    {
        $registrationData = $this->getRegistrationData();
        $registrationData['billingAddress'] = 'Max Mustermanns Address';
        unset($registrationData['shippingAddress']);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotCount(0, $response['errors']);
        static::assertSame('VIOLATION::INVALID_TYPE_ERROR', $response['errors'][0]['code']);
        static::assertSame('associative_array', $response['errors'][0]['meta']['parameters']['{{ type }}']);
        static::assertSame('/billingAddress', $response['errors'][0]['source']['pointer']);
    }

    public function testRegistrationWithBillingAddressAndWithNonArrayShippingAddress(): void
    {
        $registrationData = $this->getRegistrationData();
        $registrationData['shippingAddress'] = 'Max Mustermanns Address';

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNotCount(0, $response['errors']);
        static::assertSame('VIOLATION::AT_LEAST_ONE_OF_ERROR', $response['errors'][0]['code']);
        static::assertSame('/shippingAddress', $response['errors'][0]['source']['pointer']);
    }

    public function testRegistrationWithBillingAddressAndEmptyShippingAddress(): void
    {
        $registrationData = $this->getRegistrationData();
        unset($registrationData['shippingAddress']);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
    }

    public function testRegistrationWithExistingNotSpecifiedSalutation(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $registrationData = $this->getRegistrationData();
        unset($registrationData['salutationId']);

        $salutations = $connection->fetchAllKeyValue('SELECT salutation_key, id FROM salutation');
        static::assertArrayHasKey(SalutationDefinition::NOT_SPECIFIED, $salutations);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $criteria = new Criteria([$response['id']]);
        $criteria->addAssociation('salutation');

        $user = $this->userRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(UserEntity::class, $user);

        static::assertSame($user->getSalutation()?->getSalutationKey(), SalutationDefinition::NOT_SPECIFIED);
    }

    public function testRegistrationToNotSpecifiedWithoutExistingSalutation(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $registrationData = $this->getRegistrationData();
        unset($registrationData['salutationId']);

        $connection->executeStatement(
            '
					DELETE FROM salutation WHERE salutation_key = :salutationKey
				',
            ['salutationKey' => SalutationDefinition::NOT_SPECIFIED]
        );

        $salutations = $connection->fetchAllKeyValue('SELECT salutation_key, id FROM salutation');
        static::assertArrayNotHasKey(SalutationDefinition::NOT_SPECIFIED, $salutations);

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertNull($response['salutationId'], (string) $this->browser->getResponse()->getContent());
    }

    public function testRegistrationWithIdnEmail(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $registrationData = $this->getRegistrationData();
        $registrationData['email'] = 'teg-reg@exämple.com';

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $fetchMail = $connection->fetchOne(
            'SELECT email FROM user WHERE id = UNHEX(:userId)',
            ['userId' => $response['id']]
        );

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());

        static::assertSame('teg-reg@xn--exmple-cua.com', $fetchMail);
    }

    public function testRegisterWithTooLongPassword(): void
    {
        $registrationData = $this->getRegistrationData();
        $registrationData['password'] = \str_repeat('a', PasswordHasherInterface::MAX_PASSWORD_LENGTH + 1);
        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);

        $error = $response['errors'][0];

        static::assertSame('VIOLATION::TOO_LONG_ERROR', $error['code']);
        static::assertSame('/password', $error['source']['pointer']);
        static::assertSame(':PASSWORD_IS_TOO_LONG', $error['detail']);
    }

    public function testRegisterWithHtmlInFirstName(): void
    {
        $registrationData = $this->getRegistrationData();
        $registrationData['firstName'] = '<John';

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);

        $error = $response['errors'][0];

        static::assertSame('VIOLATION::CONTAINS_HTML_ERROR', $error['code']);
        static::assertSame('/firstName', $error['source']['pointer']);
    }

    public function testRegisterWithHtmlInBillingAddressStreet(): void
    {
        $registrationData = $this->getRegistrationData();
        $registrationData['billingAddress']['street'] = '<Main';

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(400, $this->browser->getResponse()->getStatusCode());
        static::assertArrayHasKey('errors', $response);

        $error = $response['errors'][0];

        static::assertSame('VIOLATION::CONTAINS_HTML_ERROR', $error['code']);
        static::assertSame('/billingAddress/street', $error['source']['pointer']);
    }

    public function testRegisterWithLessThanSignThatDoesNotOpenATag(): void
    {
        $registrationData = $this->getRegistrationData();
        $registrationData['firstName'] = 'Jo <3';

        $this->browser
            ->request(
                'POST',
                '/store-api/account/register',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($registrationData, \JSON_THROW_ON_ERROR)
            );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode(), (string) $this->browser->getResponse()->getContent());
        static::assertSame('user', $response['apiAlias']);
        static::assertSame('Jo <3', $response['firstName']);
    }

    private function createChannelBrowserWithoutDomains(): KernelBrowser
    {
        $browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('headless-sales-channel'),
            'domains' => [
                [
                    'id' => $this->ids->create('headless-domain'),
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://headless.example.com',
                ],
            ],
        ]);

        $this->addCountriesToChannel([], $this->ids->get('headless-sales-channel'));

        /** @var EntityRepository<ChannelDomainCollection> $domainRepository */
        $domainRepository = static::getContainer()->get('channel_domain.repository');
        $domainRepository->delete([['id' => $this->ids->get('headless-domain')]], Context::createDefaultContext());

        return $browser;
    }

    /**
     * @return array<string, mixed>
     */
    private function getRegistrationData(string $frontendUrl = 'http://localhost'): array
    {
        return [
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'password' => '12345678',
            'email' => 'teg-reg@example.com',
            'title' => 'Phd',
            'active' => true,
            'birthdayYear' => 2000,
            'birthdayMonth' => 1,
            'birthdayDay' => 22,
            'frontendUrl' => $frontendUrl,
            'billingAddress' => [
                'firstName' => 'Max',
                'lastName' => 'Mustermann',
                'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
                'street' => 'Examplestreet 11',
                'zipcode' => '48441',
                'city' => 'Cologne',
                'phoneNumber' => '0123456789',
                'additionalAddressLine1' => 'Additional address line 1',
                'additionalAddressLine2' => 'Additional address line 2',
            ],
            'shippingAddress' => [
                'salutationId' => $this->getValidSalutationId(),
                'countryId' => $this->getValidCountryId($this->ids->get('sales-channel')),
                'firstName' => 'Test 2',
                'lastName' => 'Example 2',
                'title' => 'Prof.',
                'street' => 'Examplestreet 111',
                'zipcode' => '12341',
                'city' => 'Berlin',
                'phoneNumber' => '987654321',
                'additionalAddressLine1' => 'Additional address line 01',
                'additionalAddressLine2' => 'Additional address line 02',
            ],
        ];
    }

    private function createBoundUser(string $channelId, string $email, bool $boundChannel = false): string
    {
        $userId = Uuid::randomHex();
        $addressId = Uuid::randomHex();

        $user = [
            'id' => $userId,
            'number' => '1337',
            'salutationId' => $this->getValidSalutationId(),
            'firstName' => 'Max',
            'lastName' => 'Mustermann',
            'userNumber' => '1337',
            'email' => $email,
            'password' => 'contena',
            'groupId' => TestDefaults::FALLBACK_CUSTOMER_GROUP,
            'channelId' => $channelId,
            'boundChannelId' => $boundChannel ? $channelId : null,
            'defaultBillingAddressId' => $addressId,
            'defaultShippingAddressId' => $addressId,
            'addresses' => [
                [
                    'id' => $addressId,
                    'userId' => $userId,
                    'countryId' => $this->getValidCountryId(),
                    'salutationId' => $this->getValidSalutationId(),
                    'firstName' => 'Max',
                    'lastName' => 'Mustermann',
                    'street' => 'Ebbinghoff 10',
                    'zipcode' => '48624',
                    'city' => 'Schöppingen',
                ],
            ],
        ];

        static::getContainer()
            ->get('user.repository')
            ->upsert([$user], Context::createDefaultContext());

        return $userId;
    }

    private function createBlogTestData(): void
    {
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogRepository->create([
            [
                'id' => $this->ids->create('p1'),
                'blogNumber' => $this->ids->get('p1'),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->create('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->create('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());

        $blogRepository->create([
            [
                'id' => $this->ids->create('p2'),
                'blogNumber' => $this->ids->get('p2'),
                'stock' => 10,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $this->ids->get('manufacturerId'), 'name' => 'test'],
                'tax' => ['id' => $this->ids->get('tax'), 'taxRate' => 17, 'name' => 'with id'],
                'active' => true,
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
