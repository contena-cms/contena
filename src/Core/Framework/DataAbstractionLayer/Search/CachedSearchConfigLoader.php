<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Search;

use Contena\Core\Framework\Context;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @final
 *
 * @phpstan-import-type SearchConfig from SearchConfigLoader
 */
class CachedSearchConfigLoader extends SearchConfigLoader
{
    final public const string CACHE_KEY = 'search-config';

    /**
     * @internal
     */
    public function __construct(
        private readonly SearchConfigLoader $decorated,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * @return array<SearchConfig>
     */
    public function load(Context $context): array
    {
        $cacheKey = self::CACHE_KEY;
        if ($context->getTenantId() !== null) {
            $cacheKey .= '-' . $context->getTenantId();
        }

        return $this->cache->get($cacheKey, fn (): array => $this->decorated->load($context));
    }
}
