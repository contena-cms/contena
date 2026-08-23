<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Admin\Indexer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Contena\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutEntity;
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
use Contena\Elasticsearch\Admin\Indexer\ContentLayoutAdminSearchIndexer;

/**
 * @internal
 */
#[CoversClass(ContentLayoutAdminSearchIndexer::class)]
class ContentLayoutAdminSearchIndexerTest extends TestCase
{
    private ContentLayoutAdminSearchIndexer $searchIndexer;

    protected function setUp(): void
    {
        $this->searchIndexer = new ContentLayoutAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            100
        );
    }

    public function testGetUpdatedIdsWithNameChange(): void
    {
        $indexer = new ContentLayoutAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            100
        );

        $contentLayoutId = Uuid::randomHex();

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('content_layout', [
                    new EntityWriteResult($contentLayoutId, ['name' => 'Home'], 'content_layout', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createDefaultContext()),
            ]),
            []
        );

        static::assertSame([$contentLayoutId], $indexer->getUpdatedIds($event));
    }

    public function testGetUpdatedIdsIgnoresLayoutContentChange(): void
    {
        $indexer = new ContentLayoutAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            100
        );

        $contentLayoutId = Uuid::randomHex();

        $event = new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
                new EntityWrittenEvent('content_layout', [
                    new EntityWriteResult($contentLayoutId, ['layout' => []], 'content_layout', EntityWriteResult::OPERATION_UPDATE),
                ], Context::createDefaultContext()),
            ]),
            []
        );

        static::assertSame([], $indexer->getUpdatedIds($event));
    }

    public function testGetEntity(): void
    {
        static::assertSame(ContentLayoutDefinition::ENTITY_NAME, $this->searchIndexer->getEntity());
    }

    public function testGetName(): void
    {
        static::assertSame('content-layout-listing', $this->searchIndexer->getName());
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
        $contentLayout = new ContentLayoutEntity();
        $contentLayout->setUniqueIdentifier(Uuid::randomHex());
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new EntityCollection([$contentLayout]),
                null,
                new Criteria(),
                $context
            )
        );

        $indexer = new ContentLayoutAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            $repository,
            100
        );

        $result = [
            'total' => 1,
            'hits' => [
                ['id' => '809c1844f4734243b6aa04aba860cd45'],
            ],
        ];

        $data = $indexer->globalData($result, $context);

        static::assertSame($result['total'], $data['total']);
    }

    public function testFetching(): void
    {
        $connection = $this->getConnection();

        $indexer = new ContentLayoutAdminSearchIndexer(
            $connection,
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            100
        );

        $id = '809c1844f4734243b6aa04aba860cd45';
        $documents = $indexer->fetch([$id]);

        static::assertArrayHasKey($id, $documents);

        /** @var array<string, mixed> $document */
        $document = $documents[$id];

        static::assertSame($id, $document['id']);
        static::assertSame('tenant-a', $document['tenantId']);
        static::assertSame('terms of service 1.0 page 809c1844f4734243b6aa04aba860cd45', $document['text']);
        static::assertSame('Terms of service', $document['name']);
        static::assertSame('1.0', $document['version']);
        static::assertSame('page', $document['rootSource']);
    }

    private function getConnection(): Connection
    {
        $connection = static::createStub(Connection::class);

        $connection->method('fetchAllAssociative')->willReturn(
            [
                [
                    'id' => '809c1844f4734243b6aa04aba860cd45',
                    'tenantId' => 'tenant-a',
                    'name' => 'Terms of service',
                    'version' => '1.0',
                    'rootSource' => 'page',
                ],
            ],
        );

        return $connection;
    }
}
