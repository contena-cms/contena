<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogDescriptionTeaserBuilder;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogDescriptionTeaserIndexer;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(BlogDescriptionTeaserIndexer::class)]
class BlogDescriptionTeaserIndexerTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('blog.description_teaser.indexer', $this->createIndexer()->getName());
    }

    public function testUpdateLeavesLiveWritesToSubscriber(): void
    {
        $event = new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);

        static::assertNull($this->createIndexer()->update($event));
    }

    public function testIterateReturnsMessageWithFetchedIds(): void
    {
        $query = static::createStub(IterableQuery::class);
        $query->method('fetch')->willReturn(['id-1', 'id-2']);
        $query->method('getOffset')->willReturn(['offset' => 50]);

        $factory = static::createStub(IteratorFactory::class);
        $factory->method('createIterator')->willReturn($query);

        $message = $this->createIndexer(iteratorFactory: $factory)->iterate(null);

        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        static::assertSame(['id-1', 'id-2'], $message->getData());
        static::assertSame(['offset' => 50], $message->getOffset());
    }

    public function testIterateReturnsNullWhenNoMoreIds(): void
    {
        $query = static::createStub(IterableQuery::class);
        $query->method('fetch')->willReturn([]);

        $factory = static::createStub(IteratorFactory::class);
        $factory->method('createIterator')->willReturn($query);

        static::assertNull($this->createIndexer(iteratorFactory: $factory)->iterate(null));
    }

    public function testHandleRebuildsMissingAndStaleTeasers(): void
    {
        $connection = $this->createMock(Connection::class);

        $selectSql = null;
        $connection->method('fetchAllAssociative')
            ->willReturnCallback(function (string $sql) use (&$selectSql): array {
                $selectSql = $sql;

                // Rows the `description IS NOT NULL AND NOT (description <=> description_teaser)`
                // pre-filter already lets through (raw description differs from the stored teaser).
                return [
                    // missing teaser -> filled
                    ['blog_id' => 'a', 'blog_version_id' => 'v', 'language_id' => 'l', 'description' => '<p>Hello <strong>World</strong></p>', 'description_teaser' => null],
                    // non-null but stale teaser (does not match the current description) -> rewritten
                    ['blog_id' => 'b', 'blog_version_id' => 'v', 'language_id' => 'l', 'description' => '<p>Fresh</p>', 'description_teaser' => 'Outdated'],
                    // stripped teaser is up to date even though the raw HTML differs -> skipped by the builder check
                    ['blog_id' => 'c', 'blog_version_id' => 'v', 'language_id' => 'l', 'description' => '<p>Same</p>', 'description_teaser' => 'Same'],
                ];
            });

        $updates = [];
        $connection->expects($this->exactly(2))
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$updates): int {
                $updates[$params['blogId']] = $params['teaser'];

                return 1;
            });

        $this->createIndexer(connection: $connection)->handle(new EntityIndexingMessage([Uuid::randomHex()]));

        // Reconcile: the DB pre-filters trivially-equal rows, then the teaser is rebuilt from the
        // current description and only missing or stale rows are rewritten.
        static::assertNotNull($selectSql);
        static::assertStringContainsString('description IS NOT NULL', $selectSql);
        static::assertStringContainsString('NOT (description <=> description_teaser)', $selectSql);

        static::assertSame(['a' => 'Hello World', 'b' => 'Fresh'], $updates);
    }

    public function testHandleIgnoresEmptyMessage(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $connection->expects($this->never())->method('executeStatement');

        $this->createIndexer(connection: $connection)->handle(new EntityIndexingMessage([]));
    }

    public function testGetTotalCountsBlogs(): void
    {
        $query = static::createStub(IterableQuery::class);
        $query->method('fetchCount')->willReturn(42);

        $factory = static::createStub(IteratorFactory::class);
        $factory->method('createIterator')->willReturn($query);

        static::assertSame(42, $this->createIndexer(iteratorFactory: $factory)->getTotal());
    }

    public function testGetDecoratedThrows(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->createIndexer()->getDecorated();
    }

    private function createIndexer(
        ?IteratorFactory $iteratorFactory = null,
        ?Connection $connection = null
    ): BlogDescriptionTeaserIndexer {
        return new BlogDescriptionTeaserIndexer(
            $iteratorFactory ?? static::createStub(IteratorFactory::class),
            $connection ?? static::createStub(Connection::class),
            new BlogDescriptionTeaserBuilder(
                new HtmlSanitizer(null, false, [], [BlogDescriptionTeaserBuilder::TEASER_FIELD => ['sets' => []]])
            )
        );
    }
}
