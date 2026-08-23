<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\System\Channel\BaseChannelContext;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
class CachedBaseChannelContextFactory extends AbstractBaseChannelContextFactory
{
    public function __construct(
        private readonly AbstractBaseChannelContextFactory $decorated,
        private readonly CacheInterface $cache,
    ) {
    }

    public function create(string $channelId, array $options = []): BaseChannelContext
    {
        if (isset($options[ChannelContextService::ORIGINAL_CONTEXT])) {
            return $this->decorated->create($channelId, $options);
        }
        if (isset($options[ChannelContextService::PERMISSIONS])) {
            return $this->decorated->create($channelId, $options);
        }

        $name = self::buildName($channelId);

        ksort($options);

        $keys = \array_intersect_key($options, [
            ChannelContextService::LANGUAGE_ID => true,
            ChannelContextService::DOMAIN_ID => true,
            ChannelContextService::VERSION_ID => true,
            ChannelContextService::COUNTRY_ID => true,
        ]);

        $key = implode('-', [$name, Hasher::hash($keys)]);

        $fresh = null;

        $value = $this->cache->get($key, function (ItemInterface $item) use ($name, $channelId, $options, &$fresh) {
            $item->tag([$name, CachedChannelContextFactory::ALL_TAG]);

            $fresh = $this->decorated->create($channelId, $options);

            return CacheValueCompressor::compress($fresh);
        });

        // the context was built in this call, return it directly instead of
        // uncompressing the cache payload that was just compressed from it
        if ($fresh instanceof BaseChannelContext) {
            return $fresh;
        }

        return CacheValueCompressor::uncompress($value);
    }

    public static function buildName(string $channelId): string
    {
        return 'base-context-factory-' . $channelId;
    }
}
