<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Cache\Http\CacheStore;
use Contena\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Contena\Core\Framework\Adapter\Kernel\HttpCacheKernel;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Test\AppSystemTestBehaviour;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * @internal
 */
#[Group('cache')]
class HttpCacheIntegrationTest extends TestCase
{
    use AppSystemTestBehaviour;
    use CacheTestBehaviour;
    use KernelTestBehaviour;

    private static string $originalHttpCacheValue;

    public static function setUpBeforeClass(): void
    {
        self::$originalHttpCacheValue = $_SERVER['CONTENA_HTTP_CACHE_ENABLED'] ?? '';
    }

    protected function setUp(): void
    {
        $_ENV['CONTENA_HTTP_CACHE_ENABLED'] = $_SERVER['CONTENA_HTTP_CACHE_ENABLED'] = '1';

        KernelLifecycleManager::bootKernel();

        static::getContainer()
            ->get(Connection::class)
            ->beginTransaction();
    }

    protected function tearDown(): void
    {
        $_ENV['CONTENA_HTTP_CACHE_ENABLED'] = $_SERVER['CONTENA_HTTP_CACHE_ENABLED'] = self::$originalHttpCacheValue;

        $connection = static::getContainer()->get(Connection::class);

        static::assertSame(
            1,
            $connection->getTransactionNestingLevel(),
            'Too many Nesting Levels.
            Probably one transaction was not closed properly.
            This may affect following Tests in an unpredictable manner!
            Current nesting level: "' . $connection->getTransactionNestingLevel() . '".'
        );

        $connection->rollBack();
    }

    public function testCacheHit(): void
    {
        $kernel = $this->getCacheKernel();

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $request = $this->createRequest($appUrl);

        $response = $kernel->handle($request);
        static::assertTrue($response->headers->has('x-symfony-cache'));
        $this->assertCacheHeader('GET /: miss, store', $response);

        $response = $kernel->handle($request);
        $this->assertCacheHeader('GET /: fresh', $response);
    }

    public function testCacheHashCookieChange(): void
    {
        $kernel = $this->getCacheKernel();

        $appUrl = EnvironmentHelper::getVariable('APP_URL');
        static::assertIsString($appUrl);

        $request = $this->createRequest($appUrl);

        $response = $kernel->handle($request);
        $this->assertCacheHeader('GET /: miss, store', $response);

        $response = $kernel->handle($request);
        $this->assertCacheHeader('GET /: fresh', $response);

        $request->cookies->set(HttpCacheKeyGenerator::CONTEXT_CACHE_COOKIE, 'b');

        // cache miss as request hash differs, no store as it also differs from hash calculated from context
        $response = $kernel->handle($request);
        $this->assertCacheHeader('GET /: miss', $response);
    }

    public function testCacheForAppScriptEndpointIsEnabledByDefault(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/http-cache-cases');

        $kernel = $this->getCacheKernel();

        $route = '/frontend/script/cache-default';
        $request = $this->createRequest(EnvironmentHelper::getVariable('APP_URL') . $route);

        $response = $kernel->handle($request);
        $this->assertCacheHeader(\sprintf('GET %s: miss, store', $route), $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $response = $kernel->handle($request);
        $this->assertCacheHeader(\sprintf('GET %s: fresh', $route), $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
    }

    public function testCacheForAppScriptEndpointOptOut(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/http-cache-cases');

        $kernel = $this->getCacheKernel();

        $route = '/frontend/script/cache-disable';
        $request = $this->createRequest(EnvironmentHelper::getVariable('APP_URL') . $route);

        $response = $kernel->handle($request);
        $this->assertCacheHeader(\sprintf('GET %s: miss', $route), $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $response = $kernel->handle($request);
        $this->assertCacheHeader(\sprintf('GET %s: miss', $route), $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
    }

    public function testCacheForAppScriptEndpointCustomCacheTags(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/http-cache-cases');

        $kernel = $this->getCacheKernel();

        $route = '/frontend/script/custom-cache-tags';
        $request = $this->createRequest(EnvironmentHelper::getVariable('APP_URL') . $route);

        $response = $kernel->handle($request);
        $this->assertCacheHeader(\sprintf('GET %s: miss, store', $route), $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $response = $kernel->handle($request);
        $this->assertCacheHeader(\sprintf('GET %s: fresh', $route), $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $cacheInvalidator = static::getContainer()->get(CacheInvalidator::class);
        $cacheInvalidator->invalidate(['my-custom-tag'], true);

        $response = $kernel->handle($request);
        $this->assertCacheHeader(\sprintf('GET %s: miss, store', $route), $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
    }

    public function testCacheForAppScriptEndpointCustomCacheTagsWithScriptInvalidation(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/http-cache-cases');

        $kernel = $this->getCacheKernel();

        $route = '/frontend/script/custom-cache-tags';
        $request = $this->createRequest(EnvironmentHelper::getVariable('APP_URL') . $route);

        $response = $kernel->handle($request);
        $this->assertCacheHeader(\sprintf('GET %s: miss, store', $route), $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $response = $kernel->handle($request);
        $this->assertCacheHeader(\sprintf('GET %s: fresh', $route), $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $ids = new IdsCollection();
        $blogRepo = static::getContainer()->get('blog.repository');
        // entity written event will execute the cache invalidation script, which will invalidate our custom tag
        $blogRepo->create([
            (new BlogBuilder($ids, 'p1'))
            ->price(100)
            ->build(),
        ], Context::createDefaultContext());

        $response = $kernel->handle($request);
        $this->assertCacheHeader(\sprintf('GET %s: miss, store', $route), $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));
    }

    public function testCacheForAppScriptEndpointCustomCacheConfig(): void
    {
        $this->loadAppsFromDir(__DIR__ . '/_fixtures/http-cache-cases');

        $kernel = $this->getCacheKernel();

        $route = '/frontend/script/custom-cache-config';
        $request = $this->createRequest(EnvironmentHelper::getVariable('APP_URL') . $route);

        $cacheHeaders = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(KernelEvents::RESPONSE, static function (ResponseEvent $event) use ($route, &$cacheHeaders): void {
            if ($event->getRequest()->getPathInfo() !== $route) {
                return;
            }
            $cacheHeaders[] = [
                $event->getResponse()->getMaxAge(),
                $event->getResponse()->headers->get(HttpCacheKeyGenerator::INVALIDATION_STATES_HEADER),
            ];
        });

        $response = $kernel->handle($request);
        $this->assertCacheHeader(\sprintf('GET %s: miss, store', $route), $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        $response = $kernel->handle($request);
        $this->assertCacheHeader(\sprintf('GET %s: fresh', $route), $response);
        static::assertFalse($response->headers->has(CacheStore::TAG_HEADER));

        // with v6.8.0.0 cache policies drive the headers: the script's shared max age
        // overrides the policy's s-maxage, invalidation states are removed
        $expectedInvalidationStates = Feature::isActive('v6.8.0.0') ? null : 'logged-in';
        static::assertSame([[5, $expectedInvalidationStates]], $cacheHeaders);
    }

    private function createRequest(?string $url = null): Request
    {
        if ($url === null) {
            $url = static::getContainer()->get(Connection::class)->fetchOne('SELECT url FROM channel_domain LIMIT 1');
        }

        $request = Request::create($url);

        // resolves seo urls and detects frontend sales channels
        return static::getContainer()
            ->get(RequestTransformerInterface::class)
            ->transform($request);
    }

    private function getCacheKernel(): HttpCacheKernel
    {
        return static::getContainer()->get('http_kernel.cache');
    }

    /**
     * @param non-empty-string $cacheHeaderStartsWith
     */
    private function assertCacheHeader(string $cacheHeaderStartsWith, Response $response): void
    {
        $header = $response->headers->get('x-symfony-cache');
        static::assertIsString($header);
        static::assertStringStartsWith($cacheHeaderStartsWith, $header);
    }
}
