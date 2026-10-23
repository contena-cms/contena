<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\System\Channel\BaseChannelContext;

/**
 * @internal
 */
class CachedBaseChannelContextFactory extends AbstractBaseChannelContextFactory
{
    public function __construct(
        private readonly AbstractBaseChannelContextFactory $decorated,
        private readonly InvalidationRaceAwareCache $cache,
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
            ChannelContextService::CURRENCY_ID => true,
            ChannelContextService::LANGUAGE_ID => true,
            ChannelContextService::DOMAIN_ID => true,
            ChannelContextService::PAYMENT_METHOD_ID => true,
            ChannelContextService::SHIPPING_METHOD_ID => true,
            ChannelContextService::VERSION_ID => true,
            ChannelContextService::COUNTRY_ID => true,
            ChannelContextService::COUNTRY_STATE_ID => true,
        ]);

        $key = implode('-', [$name, Hasher::hash($keys)]);
        $tags = [$name, CachedChannelContextFactory::ALL_TAG];
        $fresh = null;

        $value = $this->cache->get($key, $tags, function () use ($channelId, $options, &$fresh): string {
            $fresh = $this->decorated->create($channelId, $options);

            return CacheValueCompressor::compress($fresh);
        });

        // The context was built in this call, return it directly instead of uncompressing the cache payload that was just compressed from it.
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
