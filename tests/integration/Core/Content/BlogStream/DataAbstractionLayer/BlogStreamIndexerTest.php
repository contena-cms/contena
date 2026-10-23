<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\BlogStream\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\BlogStream\BlogStreamCollection;
use Contena\Core\Content\BlogStream\BlogStreamEntity;
use Contena\Core\Content\BlogStream\DataAbstractionLayer\BlogStreamIndexer;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;

/**
 * @internal
 */
class BlogStreamIndexerTest extends TestCase
{
    use DatabaseTransactionBehaviour;
    use KernelTestBehaviour;

    /**
     * @var EntityRepository<BlogStreamCollection>
     */
    private EntityRepository $blogStreamRepository;

    private BlogStreamIndexer $indexer;

    private Connection $connection;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepo;

    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->blogRepo = static::getContainer()->get('blog.repository');
        $this->blogStreamRepository = static::getContainer()->get('blog_stream.repository');
        $this->connection = static::getContainer()->get(Connection::class);

        $this->indexer = static::getContainer()->get(BlogStreamIndexer::class);
    }

    public function testValidRefresh(): void
    {
        $blogId = Uuid::randomHex();
        $manufacturerId = Uuid::randomHex();
        $this->blogRepo->create(
            [
                [
                    'id' => $blogId,
                    'blogNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'name' => 'Test',
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ],
            $this->context
        );

        $id = Uuid::randomHex();
        $this->connection->insert(
            'blog_stream',
            [
                'id' => Uuid::fromHexToBytes($id),
                'api_filter' => null,
                'invalid' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_translation',
            [
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Stream',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'equals',
                'field' => 'manufacturerId',
                'value' => $manufacturerId,
                'position' => 1,
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'equals',
                'field' => 'blog.id',
                'value' => $blogId,
                'position' => 2,
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $message = $this->indexer->update($this->createWrittenEvent($id));
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $this->indexer->handle($message);

        /** @var BlogStreamEntity $entity */
        $entity = $this->blogStreamRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertNotNull($entity->getApiFilter());
        static::assertCount(2, $entity->getApiFilter());

        static::assertSame('equals', $entity->getApiFilter()[0]['type']);
        static::assertSame('blog.manufacturerId', $entity->getApiFilter()[0]['field']);
        static::assertSame($manufacturerId, $entity->getApiFilter()[0]['value']);

        static::assertSame('multi', $entity->getApiFilter()[1]['type']);
        static::assertSame('OR', $entity->getApiFilter()[1]['operator']);

        /** @var array<int, array<string, string>> $queries */
        $queries = $entity->getApiFilter()[1]['queries'];
        static::assertCount(2, $queries);

        static::assertSame('equals', $queries[0]['type']);
        static::assertSame('blog.id', $queries[0]['field']);
        static::assertSame($blogId, $queries[0]['value']);

        static::assertSame('equals', $queries[1]['type']);
        static::assertSame('blog.parentId', $queries[1]['field']);
        static::assertSame($blogId, $queries[1]['value']);

        static::assertFalse($entity->isInvalid());
    }

    public function testFullIndexBuildsMappingsForNewBlogStreams(): void
    {
        $blogId = Uuid::randomHex();
        $this->blogRepo->create([
            [
                'id' => $blogId,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 10,
                'active' => true,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 19, 'name' => 'without id'],
            ],
        ], $this->context);

        $streamId = Uuid::randomHex();
        $createdAt = (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        $this->connection->insert('blog_stream', [
            'id' => Uuid::fromHexToBytes($streamId),
            'api_filter' => null,
            'invalid' => 1,
            'created_at' => $createdAt,
        ]);
        $this->connection->insert('blog_stream_translation', [
            'blog_stream_id' => Uuid::fromHexToBytes($streamId),
            'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
            'name' => 'Active blogs',
            'created_at' => $createdAt,
        ]);
        $this->connection->insert('blog_stream_filter', [
            'id' => Uuid::randomBytes(),
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
            'position' => 1,
            'blog_stream_id' => Uuid::fromHexToBytes($streamId),
            'created_at' => $createdAt,
        ]);

        static::getContainer()->get(EntityIndexerRegistry::class)->index(false);

        static::assertSame(1, (int) $this->connection->fetchOne(
            'SELECT COUNT(*) FROM blog_stream_mapping WHERE blog_id = :blogId AND blog_stream_id = :streamId',
            [
                'blogId' => Uuid::fromHexToBytes($blogId),
                'streamId' => Uuid::fromHexToBytes($streamId),
            ],
        ));
    }

    public function testWithChildren(): void
    {
        $blogId = Uuid::randomHex();
        $manufacturerId = Uuid::randomHex();
        $this->blogRepo->create(
            [
                [
                    'id' => $blogId,
                    'blogNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'name' => 'Test',
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ],
            $this->context
        );
        $id = Uuid::randomHex();

        $this->connection->insert(
            'blog_stream',
            [
                'id' => Uuid::fromHexToBytes($id),
                'api_filter' => null,
                'invalid' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_translation',
            [
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Stream',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $multiId = Uuid::randomHex();
        $this->connection->insert(
            'blog_stream_filter',
            [
                'id' => Uuid::fromHexToBytes($multiId),
                'type' => 'multi',
                'position' => 1,
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'equals',
                'field' => 'manufacturerId',
                'value' => $manufacturerId,
                'position' => 1,
                'parent_id' => Uuid::fromHexToBytes($multiId),
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'equals',
                'field' => 'blog.id',
                'operator' => 'equals',
                'value' => $blogId,
                'position' => 2,
                'parent_id' => Uuid::fromHexToBytes($multiId),
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $message = $this->indexer->update($this->createWrittenEvent($id));
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $this->indexer->handle($message);

        /** @var BlogStreamEntity $entity */
        $entity = $this->blogStreamRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertNotNull($entity->getApiFilter());
        static::assertCount(1, $entity->getApiFilter());
        static::assertSame('multi', $entity->getApiFilter()[0]['type']);
        static::assertSame(MultiFilter::CONNECTION_AND, $entity->getApiFilter()[0]['operator']);
        /** @var array<int, array<string, array<string|mixed>|string>> $childQueries */
        $childQueries = $entity->getApiFilter()[0]['queries'];
        static::assertCount(2, $childQueries);

        static::assertSame('equals', $childQueries[0]['type']);
        static::assertSame('blog.manufacturerId', $childQueries[0]['field']);
        static::assertSame($manufacturerId, $childQueries[0]['value']);

        static::assertSame('multi', $childQueries[1]['type']);
        static::assertSame('OR', $childQueries[1]['operator']);

        /** @var array<int, array<string, string>> $grandchildQueries */
        $grandchildQueries = $childQueries[1]['queries'];
        static::assertCount(2, $grandchildQueries);
        static::assertSame('equals', $grandchildQueries[0]['type']);
        static::assertSame('blog.id', $grandchildQueries[0]['field']);
        static::assertSame($blogId, $grandchildQueries[0]['value']);

        static::assertSame('equals', $grandchildQueries[1]['type']);
        static::assertSame('blog.parentId', $grandchildQueries[1]['field']);
        static::assertSame($blogId, $grandchildQueries[1]['value']);

        static::assertFalse($entity->isInvalid());
    }

    public function testInvalidType(): void
    {
        $blogId = Uuid::randomHex();
        $this->blogRepo->create(
            [
                [
                    'id' => $blogId,
                    'blogNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'name' => 'Test',
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ],
            $this->context
        );
        $id = Uuid::randomHex();

        $this->connection->insert(
            'blog_stream',
            [
                'id' => Uuid::fromHexToBytes($id),
                'api_filter' => null,
                'invalid' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_translation',
            [
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Stream',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $multiId = Uuid::randomHex();
        $this->connection->insert(
            'blog_stream_filter',
            [
                'id' => Uuid::fromHexToBytes($multiId),
                'type' => 'invalid',
                'field' => 'blog.id',
                'value' => $blogId,
                'position' => 1,
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $message = $this->indexer->update($this->createWrittenEvent($id));
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $this->indexer->handle($message);

        /** @var BlogStreamEntity $entity */
        $entity = $this->blogStreamRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertNull($entity->getApiFilter());
        static::assertTrue($entity->isInvalid());
    }

    public function testEmptyField(): void
    {
        $blogId = Uuid::randomHex();
        $this->blogRepo->create(
            [
                [
                    'id' => $blogId,
                    'blogNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'name' => 'Test',
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ],
            $this->context
        );

        $id = Uuid::randomHex();
        $this->connection->insert(
            'blog_stream',
            [
                'id' => Uuid::fromHexToBytes($id),
                'api_filter' => null,
                'invalid' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_translation',
            [
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Stream',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'equals',
                'field' => null,
                'value' => $blogId,
                'position' => 1,
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $message = $this->indexer->update($this->createWrittenEvent($id));
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $this->indexer->handle($message);

        /** @var BlogStreamEntity $entity */
        $entity = $this->blogStreamRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertNull($entity->getApiFilter());
        static::assertTrue($entity->isInvalid());
    }

    public function testEmptyValue(): void
    {
        $blogId = Uuid::randomHex();
        $this->blogRepo->create(
            [
                [
                    'id' => $blogId,
                    'blogNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'name' => 'Test',
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ],
            $this->context
        );
        $id = Uuid::randomHex();

        $this->connection->insert(
            'blog_stream',
            [
                'id' => Uuid::fromHexToBytes($id),
                'api_filter' => null,
                'invalid' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_translation',
            [
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Stream',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'equalsAny',
                'field' => 'id',
                'value' => '',
                'position' => 1,
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $message = $this->indexer->update($this->createWrittenEvent($id));
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $this->indexer->handle($message);

        /** @var BlogStreamEntity $entity */
        $entity = $this->blogStreamRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertSame([], $entity->getApiFilter());
        static::assertFalse($entity->isInvalid());
    }

    public function testWithParameters(): void
    {
        $blogId = Uuid::randomHex();
        $this->blogRepo->create(
            [
                [
                    'id' => $blogId,
                    'blogNumber' => Uuid::randomHex(),
                    'stock' => 10,
                    'name' => 'Test',
                    'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                    'manufacturer' => ['name' => 'test'],
                    'tax' => ['taxRate' => 19, 'name' => 'without id'],
                ],
            ],
            $this->context
        );
        $id = Uuid::randomHex();
        $this->connection->insert(
            'blog_stream',
            [
                'id' => Uuid::fromHexToBytes($id),
                'api_filter' => null,
                'invalid' => 1,
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_translation',
            [
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'language_id' => Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM),
                'name' => 'Stream',
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $this->connection->insert(
            'blog_stream_filter',
            [
                'id' => Uuid::randomBytes(),
                'type' => 'range',
                'field' => 'price.gross',
                'parameters' => json_encode([RangeFilter::GTE => 10]),
                'position' => 1,
                'blog_stream_id' => Uuid::fromHexToBytes($id),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]
        );

        $message = $this->indexer->update($this->createWrittenEvent($id));
        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        $this->indexer->handle($message);

        /** @var BlogStreamEntity $entity */
        $entity = $this->blogStreamRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertNotNull($entity->getApiFilter());
        static::assertCount(1, $entity->getApiFilter());
        static::assertSame('range', $entity->getApiFilter()[0]['type']);
        static::assertSame([RangeFilter::GTE => 10], $entity->getApiFilter()[0]['parameters']);
        static::assertFalse($entity->isInvalid());
    }

    private function createWrittenEvent(string $id): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent(
            $this->context,
            new NestedEventCollection([
            ]),
            []
        );
    }
}
