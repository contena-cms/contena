<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Product\Events\ProductIndexerEvent;
use Contena\Core\Content\Product\Events\ProductStockAlteredEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Contena\Elasticsearch\Product\ProductUpdater;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductUpdater::class)]
class ProductUpdaterTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            ProductIndexerEvent::class => 'update',
            ProductStockAlteredEvent::class => 'update',
        ], ProductUpdater::getSubscribedEvents());
    }

    public function testUpdate(): void
    {
        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $definition = static::createStub(EntityDefinition::class);

        $indexer->expects($this->once())->method('updateIds')->with($definition, ['id1', 'id2']);

        $event = new ProductIndexerEvent(['id1', 'id2'], Context::createDefaultContext());

        $updater = new ProductUpdater($indexer, $definition);
        $updater->update($event);
    }

    public function testStockUpdate(): void
    {
        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $definition = static::createStub(EntityDefinition::class);

        $indexer->expects($this->once())->method('updateIds')->with($definition, ['id1', 'id2']);

        $event = new ProductStockAlteredEvent(['id1', 'id2'], Context::createDefaultContext());

        $updater = new ProductUpdater($indexer, $definition);
        $updater->update($event);
    }
}
