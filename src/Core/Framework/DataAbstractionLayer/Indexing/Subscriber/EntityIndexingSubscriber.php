<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Indexing\Subscriber;

use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
class EntityIndexingSubscriber implements EventSubscriberInterface
{
    public function __construct(private readonly EntityIndexerRegistry $indexerRegistry)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [EntityWrittenContainerEvent::class => [['refreshIndex', 1000]]];
    }

    public function refreshIndex(EntityWrittenContainerEvent $event): void
    {
        $this->indexerRegistry->refresh($event);
    }
}
