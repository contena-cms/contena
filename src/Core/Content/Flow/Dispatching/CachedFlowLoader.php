<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\Dispatching;

use Contena\Core\Content\Flow\FlowEvents;
use Contena\Core\Framework\Adapter\Cache\CacheValueCompressor;
use Contena\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal not intended for decoration or replacement
 *
 * @phpstan-import-type EventGroupedFlowHolders from AbstractFlowLoader
 */
class CachedFlowLoader extends AbstractFlowLoader implements EventSubscriberInterface, ResetInterface
{
    final public const KEY = 'flow-loader';

    /**
     * @var array<string, EventGroupedFlowHolders>
     */
    private array $flows = [];

    public function __construct(
        private readonly AbstractFlowLoader $decorated,
        private readonly TagAwareCacheInterface $cache
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FlowEvents::FLOW_WRITTEN_EVENT => 'invalidate',
            FlowEvents::FLOW_DELETED_EVENT => 'invalidate',
        ];
    }

    public function load(Context $context): array
    {
        $scopeKey = self::getScopeKey($context);
        if (isset($this->flows[$scopeKey])) {
            return $this->flows[$scopeKey];
        }

        $fresh = null;

        $value = $this->cache->get(self::KEY . '-' . $scopeKey, function (ItemInterface $item) use ($context, &$fresh) {
            $item->tag([self::KEY]);

            $fresh = $this->decorated->load($context);

            return CacheValueCompressor::compress($fresh);
        });

        // the flows were loaded in this call, return them directly instead of
        // uncompressing the cache payload that was just compressed from them
        if ($fresh !== null) {
            return $this->flows[$scopeKey] = $fresh;
        }

        return $this->flows[$scopeKey] = CacheValueCompressor::uncompress($value);
    }

    public function invalidate(): void
    {
        $this->reset();
        $this->cache->invalidateTags([self::KEY]);
    }

    public function reset(): void
    {
        $this->flows = [];
    }

    private static function getScopeKey(Context $context): string
    {
        if ($context->getTenantId() !== null) {
            return 'tenant-' . $context->getTenantId();
        }

        return $context->hasGlobalTenantAccess() ? 'global' : 'platform';
    }
}
