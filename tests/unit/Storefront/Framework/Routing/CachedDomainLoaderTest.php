<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Storefront\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Storefront\Framework\Routing\CachedDomainLoader;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CachedDomainLoader::class)]
class CachedDomainLoaderTest extends TestCase
{
    public function testCachesDomainCollectionInMemory(): void
    {
        $decorated = $this->createDomainLoader();
        $cache = $this->createCache();
        $loader = new CachedDomainLoader($decorated, $cache);

        static::assertCount(1, $loader->loadDomains());
        static::assertCount(1, $loader->loadDomains());

        static::assertSame(1, $cache->getCalls);
        static::assertSame(1, $decorated->loadDomainsCalls);
    }

    public function testResetClearsDomainCollectionCache(): void
    {
        $decorated = $this->createDomainLoader();
        $cache = $this->createCache();
        $loader = new CachedDomainLoader($decorated, $cache);

        static::assertCount(1, $loader->loadDomains());

        $loader->reset();

        // After reset() memory is cleared — persistent cache is hit but inner loader is not called again
        static::assertCount(1, $loader->loadDomains());

        static::assertSame(2, $cache->getCalls);
        static::assertSame(1, $decorated->loadDomainsCalls);
    }

    /**
     * @deprecated tag:v6.8.0 - Tests deprecated load(), remove when load() is removed
     */
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCachesDomainsInMemory(): void
    {
        $decorated = $this->createDomainLoaderWithLoadCounter();
        $cache = $this->createCache();
        $loader = new CachedDomainLoader($decorated, $cache);

        static::assertSame([], $loader->load());
        // Second call must return from memory without hitting the cache or inner loader
        static::assertSame([], $loader->load());

        static::assertSame(1, $cache->getCalls);
        static::assertSame(1, $decorated->loadCalls);
    }

    /**
     * @return ArrayAdapter&object{getCalls: int}
     */
    private function createCache(): ArrayAdapter
    {
        return new class extends ArrayAdapter {
            public int $getCalls = 0;

            /**
             * @phpstan-ignore missingType.iterableValue (array type needs to be defined on external dependency)
             */
            public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
            {
                ++$this->getCalls;

                return parent::get($key, $callback, $beta, $metadata);
            }
        };
    }


}
