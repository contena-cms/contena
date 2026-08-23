<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\RateLimiter;

use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;
use League\OAuth2\Server\AuthorizationServer;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Contena\Core\Framework\Api\Controller\AuthController as AdminAuthController;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\Framework\RateLimiter\RateLimiterFactory;
use Contena\Core\Framework\Test\RateLimiter\DisableRateLimiterCompilerPass;
use Contena\Core\Framework\Test\RateLimiter\RateLimiterTestTrait;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseHelper\TestBrowser;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Api\UserRecoveryController;
use Contena\Core\System\User\Recovery\UserRecoveryService;
use Contena\Core\System\User\UserEntity;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\Policy\NoLimiter;
use Symfony\Component\RateLimiter\Storage\CacheStorage;

/**
 * @internal
 */
class RateLimiterTest extends TestCase
{
    use RateLimiterTestTrait;

    private const int TEST_THROTTLE_LIMIT = 1;

    private Context $context;

    private TestBrowser $browser;

    public static function setUpBeforeClass(): void
    {
        DisableRateLimiterCompilerPass::disableNoLimit();
        KernelLifecycleManager::bootKernel(true, Uuid::randomHex());
    }

    public static function tearDownAfterClass(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();
        KernelLifecycleManager::bootKernel(true, Uuid::randomHex());
    }

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->browser = KernelLifecycleManager::createBrowser(KernelLifecycleManager::getKernel());

        $this->clearCache();
        $this->overrideRateLimiters();
    }

    protected function tearDown(): void
    {
        DisableRateLimiterCompilerPass::enableNoLimit();
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
            $response = \json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

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
            $response = \json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

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
                        'username' => 'user' . $i,
                        'password' => 'bla',
                    ],
                    [],
                    ['REMOTE_ADDR' => '10.0.0.1']
                );

            $response = $this->browser->getResponse()->getContent();
            $response = \json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);

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
        $psrFactory = $this->createMock(PsrHttpFactory::class);
        $psrFactory->method('createRequest')->willReturn($this->createMock(ServerRequest::class));
        $psrFactory->method('createResponse')->willReturn($this->createMock(ResponseInterface::class));

        $authorizationServer = $this->createMock(AuthorizationServer::class);
        $authorizationServer->method('respondToAccessTokenRequest')->willReturn(new Response());

        $controller = new AdminAuthController(
            $authorizationServer,
            $psrFactory,
            $this->mockResetLimiter([
                RateLimiter::OAUTH => 1,
            ]),
        );

        $controller->token(new Request());
    }

    public function testRateLimitUserRecovery(): void
    {
        for ($i = 0; $i <= self::TEST_THROTTLE_LIMIT; ++$i) {
            $this->browser
                ->request(
                    'POST',
                    '/api/_action/user/user-recovery',
                    [
                        'email' => 'test@example.com',
                    ]
                );

            $response = $this->browser->getResponse()->getContent();

            if ($i >= self::TEST_THROTTLE_LIMIT) {
                static::assertJson((string) $response, (string) $response);
                $response = \json_decode((string) $response, true, 512, \JSON_THROW_ON_ERROR);
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
        $recoveryService = $this->createMock(UserRecoveryService::class);
        $userEntity = new UserEntity();
        $userEntity->setUsername('admin');
        $userEntity->setEmail('test@test.de');
        $recoveryService->method('getUserByHash')->willReturn($userEntity);
        $recoveryService->method('updatePassword')->willReturn(true);

        $controller = new UserRecoveryController(
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
            $this->createMock(SystemConfigService::class),
            new NativeClock(),
            $this->createMock(LockFactory::class),
        );

        static::assertInstanceOf(NoLimiter::class, $factory->create('example'));
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
            RateLimiter::OAUTH,
            RateLimiter::OAUTH_USER,
            RateLimiter::OAUTH_CLIENT,
            RateLimiter::USER_RECOVERY,
        ];

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
