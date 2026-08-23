<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Subscriber;

use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\System\Channel\ChannelDefinition;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
readonly class CategoryTreeMovedSubscriber implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private EntityIndexerRegistry $indexerRegistry
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EntityWrittenContainerEvent::class => 'detectChannelEntryPoints',
        ];
    }

    public function detectChannelEntryPoints(EntityWrittenContainerEvent $event): void
    {
        $properties = ['navigationCategoryId', 'footerCategoryId', 'serviceCategoryId'];

        $channelIds = $event->getPrimaryKeysWithPropertyChange(ChannelDefinition::ENTITY_NAME, $properties);

        if ($channelIds === []) {
            return;
        }

        $this->indexerRegistry->sendIndexingMessage(['category.indexer'], context: $event->getContext());
    }
}
