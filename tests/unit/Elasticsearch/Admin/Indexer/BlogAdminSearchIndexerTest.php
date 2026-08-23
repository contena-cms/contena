<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Elasticsearch\Admin\Indexer\BlogAdminSearchIndexer;
use Contena\Elasticsearch\Framework\ElasticsearchFieldBuilder;

/**
 * @internal
 */
#[CoversClass(BlogAdminSearchIndexer::class)]
class BlogAdminSearchIndexerTest extends TestCase
{
    private BlogAdminSearchIndexer $searchIndexer;

    protected function setUp(): void
    {
        $this->searchIndexer = new BlogAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );
    }

    public function testGetEntity(): void
    {
        static::assertSame(BlogDefinition::ENTITY_NAME, $this->searchIndexer->getEntity());
    }

    public function testGetName(): void
    {
        static::assertSame('blog-listing', $this->searchIndexer->getName());
    }

    public function testGetDecoratedShouldThrowException(): void
    {
        static::expectException(DecorationPatternException::class);
        $this->searchIndexer->getDecorated();
    }

    public function testGlobalData(): void
    {
        $context = Context::createDefaultContext();
        $repository = static::createStub(EntityRepository::class);
        $blog = new BlogEntity();
        $blog->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new EntityCollection([$blog]),
                null,
                new Criteria(),
                $context
            )
        );
        $indexer = new BlogAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            $repository,
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );

        $data = $indexer->globalData([
            'total' => 1,
            'hits' => [['id' => '809c1844f4734243b6aa04aba860cd45']],
        ], $context);

        static::assertSame(1, $data['total']);
        static::assertCount(1, $data['data']);
    }

    public function testFetching(): void
    {
        $indexer = new BlogAdminSearchIndexer(
            $this->getConnection(),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );
        $id = '809c1844f4734243b6aa04aba860cd45';

        $documents = $indexer->fetch([$id]);

        static::assertArrayHasKey($id, $documents);
        $document = $documents[$id];
        static::assertSame($id, $document['id']);
        static::assertSame('tenant-a', $document['tenantId']);
        static::assertSame('blog tag ' . $id, $document['text']);
        static::assertSame('keywords', $document['textBoosted']);
        static::assertSame(['blog'], $document['completion']);
        static::assertTrue($document['active']);
        static::assertSame(['b7d2554b0ce847cd82f3ac9bd1c0dfca' => 'Blog'], $document['name']);
        static::assertSame(['category-1'], $document['categoryIds']);
        static::assertSame(['tag-1'], $document['tagIds']);
        static::assertSame(['channel-1'], $document['channelIds']);
        static::assertSame('cover-1', $document['coverId']);
        static::assertSame('open-graph-1', $document['openGraphMediaId']);
        static::assertSame([['id' => 'tag-1', '_count' => 1]], $document['tags']);
        static::assertSame('2026-08-01 12:00:00.000', $document['releaseDate']);
        static::assertSame('2026-07-01 12:00:00.000', $document['createdAt']);
    }

    public function testGetUpdatedIds(): void
    {
        $blogId = Uuid::randomHex();
        /** @var NestedEventCollection<EntityWrittenEvent<string|array<string, string>>> $events */
        $events = new NestedEventCollection([
            new EntityWrittenEvent('blog', [
                new EntityWriteResult($blogId, ['active' => true], 'blog', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
            new EntityWrittenEvent('blog_translation', [
                new EntityWriteResult(['blogId' => $blogId], ['name' => 'New name'], 'blog_translation', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
            new EntityWrittenEvent('blog_category', [
                new EntityWriteResult(['blogId' => $blogId, 'categoryId' => Uuid::randomHex()], ['categoryId' => Uuid::randomHex()], 'blog_category', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
            new EntityWrittenEvent('blog_visibility', [
                new EntityWriteResult(['blogId' => $blogId, 'channelId' => Uuid::randomHex()], ['visibility' => 30], 'blog_visibility', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
            new EntityWrittenEvent('blog_media', [
                new EntityWriteResult(Uuid::randomHex(), ['blogId' => $blogId, 'mediaId' => Uuid::randomHex()], 'blog_media', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
            new EntityWrittenEvent('blog_tag', [
                new EntityWriteResult(['blogId' => $blogId, 'tagId' => Uuid::randomHex()], ['tagId' => Uuid::randomHex()], 'blog_tag', EntityWriteResult::OPERATION_UPDATE),
            ], Context::createDefaultContext()),
        ]);
        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            $events,
            []
        );

        static::assertSame([$blogId], $this->searchIndexer->getUpdatedIds($event));
    }

    private function getConnection(): Connection
    {
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([[
            'id' => '809c1844f4734243b6aa04aba860cd45',
            'tenantId' => 'tenant-a',
            'name' => 'Blog',
            'translatedNames' => '[{"languageId":"b7d2554b0ce847cd82f3ac9bd1c0dfca","name":"Blog"}]',
            'customSearchKeywords' => '[["keywords"]]',
            'tags' => 'Tag',
            'tagIds' => 'tag-1',
            'categoryIds' => 'category-1',
            'channelIds' => 'channel-1',
            'active' => 1,
            'coverId' => 'cover-1',
            'openGraphMediaId' => 'open-graph-1',
            'releaseDate' => '2026-08-01 12:00:00.000',
            'createdAt' => '2026-07-01 12:00:00.000',
        ]]);

        return $connection;
    }
}
