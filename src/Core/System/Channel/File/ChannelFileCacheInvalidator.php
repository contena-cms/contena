<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\File;

use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Contena\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
use Contena\Core\Framework\Update\Event\UpdatePostFinishEvent;
use Contena\Core\System\Channel\ChannelException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class ChannelFileCacheInvalidator implements EventSubscriberInterface
{
    private const DISCOVERY_TAG = 'channel-file-discovery';

    public function __construct(private readonly CacheInvalidator $cacheInvalidator)
    {
    }

    public static function buildCacheTag(string $channelFileId): string
    {
        // A channel_file row is the persisted ownership boundary for exactly one public file
        // in one channel. Runtime response invalidation only needs the row-specific tag;
        // template discovery has its own tag for extension and update lifecycle changes.
        return 'channel-file-' . $channelFileId;
    }

    public static function buildDiscoveryCacheTag(): string
    {
        return self::DISCOVERY_TAG;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'channel_file.written' => 'invalidate',
            'channel_file.deleted' => 'invalidate',
            PluginPostActivateEvent::class => 'invalidateDiscovery',
            PluginPostDeactivateEvent::class => 'invalidateDiscovery',
            PluginPostUpdateEvent::class => 'invalidateDiscovery',
            UpdatePostFinishEvent::class => 'invalidateDiscovery',
        ];
    }

    public function invalidate(EntityWrittenEvent|EntityDeletedEvent $event): void
    {
        $tags = [];

        foreach ($event->getWriteResults() as $writeResult) {
            $tags[] = self::buildCacheTag($this->getPrimaryKeyId($writeResult));
        }

        // Force immediate invalidation because Admin edits should update the public file response directly.
        // This only purges row-specific tags for actually touched files, so it cannot fan out into a cache storm.
        $this->cacheInvalidator->invalidate(array_values(array_unique($tags)), true);
    }

    public function invalidateDiscovery(): void
    {
        // Template discovery caches the resolved Twig chain. Plugin lifecycle changes and Contena
        // updates can change that chain, so clear the single discovery tag immediately after those events.
        $this->cacheInvalidator->invalidate([self::buildDiscoveryCacheTag()], true);
    }

    private function getPrimaryKeyId(EntityWriteResult $writeResult): string
    {
        $primaryKey = $writeResult->getPrimaryKey();

        // This subscriber only listens to entities with a single field primary key, so a combined primary key should never occur here.
        if (!\is_string($primaryKey)) {
            throw ChannelException::unexpectedCombinedPrimaryKey($writeResult->getEntityName());
        }

        return $primaryKey;
    }
}
