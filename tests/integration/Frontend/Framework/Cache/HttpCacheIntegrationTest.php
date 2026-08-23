<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Frontend\Framework\Cache;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\DevOps\Environment\EnvironmentHelper;
use Contena\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Contena\Core\Framework\Adapter\Kernel\HttpCacheKernel;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Group('cache')]
class HttpCacheIntegrationTest extends TestCase
{
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

        $response = $kernel->handle($request);
        $this->assertCacheHeader('GET /: miss', $response);
    }

    private function createRequest(?string $url = null): Request
    {
        if ($url === null) {
            $url = static::getContainer()->get(Connection::class)->fetchOne('SELECT url FROM channel_domain LIMIT 1');
        }

        $request = Request::create($url);

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
