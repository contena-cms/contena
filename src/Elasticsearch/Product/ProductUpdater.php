<?php declare(strict_types=1);

namespace Contena\Elasticsearch\Product;

use Contena\Core\Content\Product\Events\ProductIndexerEvent;
use Contena\Core\Content\Product\Events\ProductStockAlteredEvent;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class ProductUpdater implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ElasticsearchIndexer $indexer,
        private readonly EntityDefinition $definition
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductIndexerEvent::class => 'update',
            ProductStockAlteredEvent::class => 'update',
        ];
    }

    public function update(ProductIndexerEvent|ProductStockAlteredEvent $event): void
    {
        $this->indexer->updateIds($this->definition, $event->getIds());
    }
}
