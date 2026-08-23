<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing;

use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Frontend\Theme\Aggregate\ThemeChannelDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class CachedDomainLoaderInvalidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(private readonly CacheInvalidator $logger)
    {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => [
                ['invalidate', 2000],
            ],
        ];
    }

    public function invalidate(EntityWrittenContainerEvent $event): void
    {
        if (!$event->getEventByEntityName(ChannelDefinition::ENTITY_NAME)
            && !$event->getEventByEntityName(ThemeChannelDefinition::ENTITY_NAME)) {
            return;
        }

        $this->logger->invalidate([
            CachedDomainLoader::DOMAIN_COLLECTION_CACHE_KEY,
        ], true);
    }
}
