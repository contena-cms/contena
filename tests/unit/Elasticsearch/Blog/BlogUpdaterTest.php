<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Events\BlogIndexerEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Elasticsearch\Blog\BlogUpdater;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;

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
        ], BlogUpdater::getSubscribedEvents());
    }

    public function testUpdate(): void
    {
        $indexer = $this->createMock(ElasticsearchIndexer::class);
        $definition = static::createStub(EntityDefinition::class);

        $context = Context::createTenantContext('tenant-id');
        $indexer->expects($this->once())->method('updateIds')->with($definition, ['id1', 'id2'], $context);

        $event = new BlogIndexerEvent(['id1', 'id2'], $context);

        $updater = new BlogUpdater($indexer, $definition);
        $updater->update($event);
    }
}
