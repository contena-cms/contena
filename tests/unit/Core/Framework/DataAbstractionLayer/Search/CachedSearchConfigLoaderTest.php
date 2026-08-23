<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\CachedSearchConfigLoader;
use Contena\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[CoversClass(CachedSearchConfigLoader::class)]
class CachedSearchConfigLoaderTest extends TestCase
{
    public function testTenantContextUsesTenantSpecificCacheEntry(): void
    {
        $context = Context::createTenantContext('tenant-a');
        $decorated = $this->createMock(SearchConfigLoader::class);
        $cache = $this->createMock(CacheInterface::class);

        $config = [['and_logic' => '1', 'excluded_terms' => [], 'min_search_length' => 2, 'field' => 'name', 'tokenize' => 1, 'ranking' => 1.0, 'use_exact_subfield' => 1]];

        $decorated->expects($this->once())
            ->method('load')
            ->with($context)
            ->willReturn($config);

        $cache->expects($this->once())
            ->method('get')
            ->with('search-config-tenant-a', static::isInstanceOf(\Closure::class))
            ->willReturnCallback(static fn (string $key, callable $callback): array => $callback());

        $loader = new CachedSearchConfigLoader($decorated, $cache);

        static::assertSame($config, $loader->load($context));
    }

    public function testPlatformContextKeepsTheBaseCacheEntry(): void
    {
        $context = Context::createDefaultContext();
        $decorated = static::createStub(SearchConfigLoader::class);
        $cache = $this->createMock(CacheInterface::class);

        $cache->expects($this->once())
            ->method('get')
            ->with(CachedSearchConfigLoader::CACHE_KEY, static::isInstanceOf(\Closure::class))
            ->willReturn([]);

        $loader = new CachedSearchConfigLoader($decorated, $cache);

        static::assertSame([], $loader->load($context));
    }
}
