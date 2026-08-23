<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig;

use Contena\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Contena\Core\Framework\Context;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CachedSystemConfigLoader extends AbstractSystemConfigLoader
{
    final public const string CACHE_TAG = 'system-config';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractSystemConfigLoader $decorated,
        private readonly CacheInterface $cache
    ) {
    }

    public function getDecorated(): AbstractSystemConfigLoader
    {
        return $this->decorated;
    }

    public function load(?string $channelId, ?Context $context = null): array
    {
        $key = 'system-config-' . ($channelId ?? 'global') . '-' . $this->getContextKey($context);

        $fresh = null;

        $value = $this->cache->get($key, function (ItemInterface $item) use ($channelId, $context, &$fresh) {
            $fresh = $this->getDecorated()->load($channelId, $context);

            $item->tag([self::CACHE_TAG]);

            return CacheValueCompressor::compress($fresh);
        });

        // the config was loaded in this call, return it directly instead of
        // uncompressing the cache payload that was just compressed from it
        if ($fresh !== null) {
            return $fresh;
        }

        return CacheValueCompressor::uncompress($value);
    }

    private function getContextKey(?Context $context): string
    {
        if ($context === null) {
            return 'implicit';
        }

        if ($context->getTenantId() !== null) {
            return 'tenant-' . $context->getTenantId();
        }

        return $context->hasGlobalTenantAccess() ? 'global' : 'platform';
    }
}
