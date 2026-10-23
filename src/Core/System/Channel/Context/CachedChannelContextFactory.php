<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Context;

use Contena\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Contena\Core\Framework\Util\Hasher;
use Contena\Core\System\Channel\ChannelContext;

class CachedChannelContextFactory extends AbstractChannelContextFactory
{
    final public const ALL_TAG = 'sales-channel-context';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractChannelContextFactory $decorated,
        private readonly InvalidationRaceAwareCache $cache,
    ) {
    }

    public function getDecorated(): AbstractChannelContextFactory
    {
        return $this->decorated;
    }

    public function create(string $token, string $channelId, array $options = []): ChannelContext
    {
        $name = self::buildName($channelId);

        if (!$this->isCacheable($options)) {
            return $this->getDecorated()->create($token, $channelId, $options);
        }

        ksort($options);

        $key = implode('-', [$name, Hasher::hash($options)]);
        $tags = [$name, self::ALL_TAG];
        $fresh = null;

        $value = $this->cache->get($key, $tags, function () use ($token, $channelId, $options, &$fresh): string {
            $fresh = $this->decorated->create($token, $channelId, $options);

            return CacheValueCompressor::compress($fresh);
        });

        // The context was built in this call, return it directly instead of uncompressing the cache payload that was just compressed from it.
        if ($fresh instanceof ChannelContext) {
            return $fresh;
        }

        $context = CacheValueCompressor::uncompress($value);

        if (!$context instanceof ChannelContext) {
            return $this->getDecorated()->create($token, $channelId, $options);
        }

        $context->assign(['token' => $token]);

        return $context;
    }

    public static function buildName(string $channelId): string
    {
        return 'context-factory-' . $channelId;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function isCacheable(array $options): bool
    {
        return !isset($options[ChannelContextService::CUSTOMER_ID])
            && !isset($options[ChannelContextService::BILLING_ADDRESS_ID])
            && !isset($options[ChannelContextService::SHIPPING_ADDRESS_ID])
            && !isset($options[ChannelContextService::BILLING_ADDRESS])
            && !isset($options[ChannelContextService::SHIPPING_ADDRESS]);
    }
}
