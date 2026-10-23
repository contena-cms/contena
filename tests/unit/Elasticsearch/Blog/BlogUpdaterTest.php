<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Events\BlogIndexerEvent;
use Contena\Core\Content\Blog\Events\BlogStockAlteredEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Contena\Elasticsearch\Blog\BlogUpdater;

/**
 * @internal
 */
#[CoversClass(BlogUpdater::class)]
class BlogUpdaterTest extends TestCase
{
    public function testGetSubscribedEvents(): void
    {
        static::assertSame([
            BlogIndexerEvent::class => 'update',
            BlogStockAlteredEvent::class => 'update',
        ], BlogUpdater::getSubscribedEvents());
    }

    public function testUpdate(): void
    {
        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $definition = static::createStub(EntityDefinition::class);

        $indexer->expects($this->once())->method('updateIds')->with($definition, ['id1', 'id2']);

        $event = new BlogIndexerEvent(['id1', 'id2'], Context::createDefaultContext());

        $updater = new BlogUpdater($indexer, $definition);
        $updater->update($event);
    }

    public function testStockUpdate(): void
    {
        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $definition = static::createStub(EntityDefinition::class);

        $indexer->expects($this->once())->method('updateIds')->with($definition, ['id1', 'id2']);

        $event = new BlogStockAlteredEvent(['id1', 'id2'], Context::createDefaultContext());

        $updater = new BlogUpdater($indexer, $definition);
        $updater->update($event);
    }
}
