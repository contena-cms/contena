<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\RateLimiter;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\System\User\Channel\LoginRoute;
use Contena\Core\Content\Newsletter\Channel\NewsletterSubscribeRoute;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Controller\AuthController as AdminAuthController;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\RateLimiter\RateLimiterFactory;
use Contena\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Contena\Core\Framework\Test\RateLimiter\RateLimiterTestTrait;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\AdminUser\Api\AdminUserRecoveryController;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryService;
use Contena\Core\System\AdminUser\AdminUserEntity;
use Contena\Core\Test\Integration\Traits\UserTestTrait;
use Contena\Core\Test\Integration\Traits\OrderFixture;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * @internal
 */
class RateLimiterTest extends TestCase
{
    use UserTestTrait;
    use OrderFixture;
    use RateLimiterTestTrait;

    private const TEST_THROTTLE_LIMIT = 1;

    private Context $context;

    private IdsCollection $ids;

    private KernelBrowser $browser;

    private AbstractChannelContextFactory $channelContextFactory;

    private static bool $rateLimitedKernelBooted = false;

    public static function setUpBeforeClass(): void
    {
        DisableRateLimiterCompilerPass::disableNoLimit();
    }

    public static function tearDownAfterClass(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();
        // shut down only: the next class boots its kernel lazily inside a test context, which
        // recompiles with the rate limiter restored
        KernelLifecycleManager::ensureKernelShutdown();
        self::$rateLimitedKernelBooted = false;
    }

    protected function setUp(): void
    {
        // the rate-limiter pass applies at container compile time, so this class needs a freshly
        // compiled kernel. It is booted here rather than in setUpBeforeClass(): a deprecation
        // triggered during a static-context kernel boot has no TestCase object on the call stack
        // and crashes PHPUnit's event system instead of being recorded
        if (!self::$rateLimitedKernelBooted) {
            KernelLifecycleManager::bootKernel(true, Uuid::randomHex());
            self::$rateLimitedKernelBooted = true;
        }

        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel'),
        ]);
        $this->assignChannelContext($this->browser);

        $this->channelContextFactory = static::getContainer()->get(ChannelContextFactory::class)->getDecorated();

        $this->clearCache();
        $this->overrideRateLimiters();
    }

    protected function tearDown(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();
    }

    public function testRateLimitLoginRoute(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $password = 'wrongPassword';
        $this->createUser($email);

        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/store-api/account/login',
                    [
                        'email' => $email,
                        'password' => $password,
                    ]
                );

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('CHECKOUT__CUSTOMER_AUTH_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertSame(401, (int) $response['errors'][0]['status']);
                static::assertSame('Unauthorized', $response['errors'][0]['title']);
            }
        }
    }

    public function testRateLimitLoginRouteByUserWithRotatingIps(): void
    {
        $email = Uuid::randomHex() . '@example.com';
        $this->createUser($email);

        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/store-api/account/login',
                    [
                        'email' => $email,
                        'password' => 'wrongPassword',
                    ],
                    [],
                    ['REMOTE_ADDR' => '10.0.0.' . $i]
                );

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('CHECKOUT__CUSTOMER_AUTH_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertSame(401, (int) $response['errors'][0]['status']);
            }
        }
    }

    public function testRateLimitLoginRouteByClientWithRotatingEmails(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $email = 'admin_user' . $i . '@example.com';
            $this->createUser($email);

            $this->browser
                ->request(
                    'POST',
                    '/store-api/account/login',
                    [
                        'email' => $email,
                        'password' => 'wrongPassword',
                    ],
                    [],
                    ['REMOTE_ADDR' => '10.0.0.1']
                );

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('CHECKOUT__CUSTOMER_AUTH_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertSame(401, (int) $response['errors'][0]['status']);
            }
        }
    }

    public function testResetRateLimitLoginRoute(): void
    {
        $route = new LoginRoute(
            static::getContainer()->get(AccountService::class),
            static::getContainer()->get('request_stack'),
            $this->mockResetLimiter([
                RateLimiter::LOGIN_ROUTE => 1,
            ]),
            new ExtensionDispatcher(new EventDispatcher())
        );

        $this->createUser('loginTest@example.com');

        static::getContainer()->get('request_stack')->push(new Request([
            'email' => 'loginTest@example.com',
            'password' => 'contena',
        ]));

        $route->login(new RequestDataBag([
            'email' => 'loginTest@example.com',
            'password' => 'contena',
        ]), $this->channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL));
    }

    public function testRateLimitOauth(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/api/oauth/token',
                    [
                        'grant_type' => 'password',
                        'client_id' => 'administration',
                        'username' => 'admin',
                        'password' => 'bla',
                    ]
                );

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__NOTIFICATION_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertSame(400, (int) $response['errors'][0]['status']);
                static::assertSame('6', $response['errors'][0]['code']);
            }
        }
    }

    public function testRateLimitOauthByUserWithRotatingIps(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/api/oauth/token',
                    [
                        'grant_type' => 'password',
                        'client_id' => 'administration',
                        'username' => 'admin',
                        'password' => 'bla',
                    ],
                    [],
                    ['REMOTE_ADDR' => '10.0.0.' . $i]
                );

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__NOTIFICATION_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertSame(400, (int) $response['errors'][0]['status']);
                static::assertSame('6', $response['errors'][0]['code']);
            }
        }
    }

    public function testRateLimitOauthByClientWithRotatingUsernames(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/api/oauth/token',
                    [
                        'grant_type' => 'password',
                        'client_id' => 'administration',
                        'username' => 'admin_user' . $i,
                        'password' => 'bla',
                    ],
                    [],
                    ['REMOTE_ADDR' => '10.0.0.1']
                );

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            static::assertArrayHasKey('errors', $response);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__NOTIFICATION_THROTTLED', $response['errors'][0]['code']);
            } else {
                static::assertSame(400, (int) $response['errors'][0]['status']);
                static::assertSame('6', $response['errors'][0]['code']);
            }
        }
    }

    public function testResetRateLimitOauth(): void
    {
        $psrFactory = static::createStub(PsrHttpFactory::class);
        $psrFactory->method('createRequest')->willReturn(static::createStub(ServerRequest::class));
        $psrFactory->method('createResponse')->willReturn(static::createStub(ResponseInterface::class));

        $authorizationServer = static::createStub(AuthorizationServer::class);
        $authorizationServer->method('respondToAccessTokenRequest')->willReturn(new Response());

        $controller = new AdminAuthController(
            $authorizationServer,
            $psrFactory,
            $this->mockResetLimiter([
                RateLimiter::OAUTH => 1,
            ]),
            static::getContainer()->get(Connection::class),
        );

        $controller->token(new Request());
    }

    public function testRateLimitContactForm(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/store-api/contact-form',
                    [
                        'salutationId' => $this->getValidSalutationId(),
                        'firstName' => 'John',
                        'lastName' => 'Doe',
                        'email' => 'test@example.com',
                        'phone' => '+49123456789',
                        'subject' => 'Test contact request',
                        'comment' => 'Hello, this is my test request.',
                    ]
                );

            $response = $this->browser->getResponse()->getContent();
            $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertArrayHasKey('errors', $response, print_r($response, true));
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__RATE_LIMIT_EXCEEDED', $response['errors'][0]['code']);
            } else {
                static::assertSame(200, $this->browser->getResponse()->getStatusCode());
            }
        }
    }

    public function testRateLimitCartAddLineItemPerChannel(): void
    {
        $systemConfig = static::getContainer()->get(SystemConfigService::class);
        $scopedChannelId = $this->ids->get('sales-channel');

        $otherBrowser = $this->createCustomChannelBrowser([
            'id' => $this->ids->create('sales-channel-2'),
            'domains' => [
                [
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'currencyId' => Defaults::CURRENCY,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'http://localhost.second-channel',
                ],
            ],
        ]);
        $this->assignChannelContext($otherBrowser);

        $blog = (new BlogBuilder($this->ids, 'rate-limited-blog'))
            ->price(10)
            ->visibility($scopedChannelId)
            ->visibility($this->ids->get('sales-channel-2'))
            ->build();
        static::getContainer()->get('blog.repository')->create([$blog], $this->context);
        $blogId = $this->ids->get('rate-limited-blog');

        // a single-entry time_backoff limit allows the configured number of attempts inside the
        // interval, so a value of 1 allows one addition and throttles the second; the global
        // value is pinned explicitly so ambient state cannot throttle the second channel
        $systemConfig->set('core.cart.lineItemAddLimit', null);
        $systemConfig->set('core.cart.lineItemAddLimit', self::TEST_THROTTLE_LIMIT, $scopedChannelId);

        try {
            for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
                $this->addBlogToCart($this->browser, $blogId);

                if ($i >= self::TEST_THROTTLE_LIMIT) {
                    static::assertSame(429, $this->browser->getResponse()->getStatusCode());

                    $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

                    static::assertArrayHasKey('errors', $response, print_r($response, true));
                    static::assertSame(429, (int) $response['errors'][0]['status']);
                    static::assertSame('FRAMEWORK__RATE_LIMIT_EXCEEDED', $response['errors'][0]['code']);
                } else {
                    static::assertSame(200, $this->browser->getResponse()->getStatusCode());
                }
            }

            // same blog and client ip on a second sales channel without an override: not throttled
            for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
                $this->addBlogToCart($otherBrowser, $blogId);

                static::assertSame(200, $otherBrowser->getResponse()->getStatusCode(), (string) $otherBrowser->getResponse()->getContent());
            }
        } finally {
            $systemConfig->set('core.cart.lineItemAddLimit', null, $scopedChannelId);
            $systemConfig->set('core.cart.lineItemAddLimit', null);
        }
    }

    public function testRateLimitUserRecovery(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/api/_action/admin_user/admin_user-recovery',
                    [
                        'email' => 'test@example.com',
                    ]
                );

            $response = $this->browser->getResponse()->getContent();

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertJson((string) $response, (string) $response);
                $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);
                static::assertIsArray($response);
                static::assertArrayHasKey('errors', $response);
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__RATE_LIMIT_EXCEEDED', $response['errors'][0]['code']);
            } else {
                static::assertSame(200, $this->browser->getResponse()->getStatusCode());
            }
        }
    }

    public function testResetRateLimtitUserRecovery(): void
    {
        $recoveryService = static::createStub(AdminUserRecoveryService::class);
        $admin_userEntity = new AdminUserEntity();
        $admin_userEntity->setUsername('admin');
        $admin_userEntity->setEmail('test@test.de');
        $recoveryService->method('getUserByHash')->willReturn($admin_userEntity);
        $recoveryService->method('updatePassword')->willReturn(true);

        $controller = new AdminUserRecoveryController(
            $recoveryService,
            $this->mockResetLimiter([
                RateLimiter::OAUTH => 1,
                RateLimiter::USER_RECOVERY => 1,
            ]),
        );

        $controller->updateUserPassword(new Request(), $this->context);
    }

    public function testItThrowsExceptionOnInvalidRoute(): void
    {
        $rateLimiter = new RateLimiter();

        $this->expectException(\RuntimeException::class);
        $rateLimiter->reset('test', 'test-key');
    }

    public function testIgnoreLimitWhenDisabled(): void
    {
        $config = [
            'enabled' => false,
            'id' => 'test_limit',
            'policy' => 'time_backoff',
            'reset' => '5 minutes',
            'limits' => [
                [
                    'limit' => 3,
                    'interval' => '10 seconds',
                ],
            ],
        ];

        $factory = new RateLimiterFactory(
            $config,
            new CacheStorage(new ArrayAdapter()),
            static::createStub(SystemConfigService::class),
            new NativeClock(),
            static::createStub(LockFactory::class),
        );

        static::assertInstanceOf(NoLimiter::class, $factory->create('example'));
    }

    public function testRateLimitNewsletterSubscribeForm(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/store-api/newsletter/subscribe',
                    [
                        'email' => 'test@example.com',
                        'option' => 'subscribe',
                        'frontendUrl' => 'http://localhost',
                    ]
                );

            $response = $this->browser->getResponse()->getContent();

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertJson((string) $response);
                $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

                static::assertArrayHasKey('errors', $response);
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__RATE_LIMIT_EXCEEDED', $response['errors'][0]['code']);
            } else {
                static::assertSame(200, $this->browser->getResponse()->getStatusCode());
            }
        }
    }

    public function testRateLimitNewsletterUnsubscribeForm(): void
    {
        $emailList = [
            'testOne@example.com',
            'testTwo@example.com',
        ];

        $this->createNewsletterRecipient($emailList);

        foreach ($emailList as $email) {
            $this->browser
                ->request(
                    'POST',
                    '/store-api/newsletter/unsubscribe',
                    [
                        'email' => $email,
                    ]
                );

            $response = $this->browser->getResponse()->getContent();

            if ($email === 'testTwo@example.com') {
                static::assertJson((string) $response);
                $response = json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

                static::assertArrayHasKey('errors', $response);
                static::assertSame(429, (int) $response['errors'][0]['status']);
                static::assertSame('FRAMEWORK__RATE_LIMIT_EXCEEDED', $response['errors'][0]['code']);
            } else {
                static::assertSame(200, $this->browser->getResponse()->getStatusCode());
            }
        }
    }

    /**
     * @param List<string> $emailList
     */
    private function createNewsletterRecipient(array $emailList): void
    {
        $newsletterRecipients = [];
        foreach ($emailList as $email) {
            $newsletterRecipients[] = [
                'email' => $email,
                'status' => NewsletterSubscribeRoute::STATUS_DIRECT,
                'hash' => Uuid::randomHex(),
                'channelId' => $this->ids->get('sales-channel'),
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ];
        }

        $this->getContainer()->get('newsletter_recipient.repository')->upsert($newsletterRecipients, $this->context);
    }

    private function addBlogToCart(KernelBrowser $browser, string $blogId): void
    {
        $browser->request(
            'POST',
            '/store-api/checkout/cart/line-item',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([
                'items' => [
                    [
                        'type' => 'blog',
                        'referencedId' => $blogId,
                        'quantity' => 1,
                    ],
                ],
            ], \JSON_THROW_ON_ERROR)
        );
    }

    private function overrideRateLimiters(): void
    {
        $limitOneConfig = [
            'enabled' => true,
            'policy' => 'time_backoff',
            'reset' => '1 hour',
            'limits' => [['limit' => 1, 'interval' => '1 hour']],
        ];

        $routes = [
            RateLimiter::LOGIN_ROUTE,
            RateLimiter::LOGIN_USER,
            RateLimiter::LOGIN_CLIENT,
            RateLimiter::OAUTH,
            RateLimiter::OAUTH_USER,
            RateLimiter::OAUTH_CLIENT,
            RateLimiter::CONTACT_FORM,
            RateLimiter::USER_RECOVERY,
            RateLimiter::NEWSLETTER_FORM,
            RateLimiter::NEWSLETTER_UNSUBSCRIBE_FORM,
        ];

        // LoginRoute is injected with RateLimiter::class, AuthController with 'contena.rate_limiter'
        // These may be separate instances in the compiled container, so override both
        foreach ([RateLimiter::class, 'contena.rate_limiter'] as $serviceId) {
            $rateLimiter = static::getContainer()->get($serviceId);
            \assert($rateLimiter instanceof RateLimiter);
            foreach ($routes as $name) {
                $rateLimiter->registerLimiterFactory($name, new RateLimiterFactory(
                    $limitOneConfig + ['id' => $name],
                    new CacheStorage(new ArrayAdapter()),
                    static::createStub(SystemConfigService::class),
                    new NativeClock(),
                ));
            }
        }
    }
}
