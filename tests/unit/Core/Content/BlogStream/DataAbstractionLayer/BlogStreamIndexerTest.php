<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\BlogStream\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\BlogStream\BlogStreamCollection;
use Contena\Core\Content\BlogStream\BlogStreamDefinition;
use Contena\Core\Content\BlogStream\DataAbstractionLayer\BlogStreamIndexer;
use Contena\Core\Content\BlogStream\DataAbstractionLayer\BlogStreamIndexingMessage;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\OffsetQuery;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\QueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\QueryStringParser;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\Serializer;

/**
 * @internal
 */
#[CoversClass(BlogStreamIndexer::class)]
class BlogStreamIndexerTest extends TestCase
{
    private Connection&Stub $connection;

    private IteratorFactory&Stub $iteratorFactory;

    private BlogDefinition&Stub $blogDefinition;

    private BlogStreamIndexer $indexer;

    private Stub&EventDispatcherInterface $dispatcher;

    /**
     * @var StaticEntityRepository<BlogStreamCollection>
     */
    private StaticEntityRepository $repository;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->iteratorFactory = static::createStub(IteratorFactory::class);
        $this->blogDefinition = static::createStub(BlogDefinition::class);
        $this->repository = new StaticEntityRepository([], new BlogStreamDefinition());
        $this->dispatcher = static::createStub(EventDispatcherInterface::class);

        $this->indexer = $this->createIndexer();
    }

    public function testGetName(): void
    {
        static::assertSame('blog_stream.indexer', $this->indexer->getName());
    }

    public function testIterate(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchAllKeyValue')->willReturn([123]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->once())->method('createIterator')->willReturn(new OffsetQuery($queryBuilder));

        $message = $this->createIndexer(iteratorFactory: $iteratorFactory)->iterate(['offset' => 10]);
        static::assertInstanceOf(BlogStreamIndexingMessage::class, $message);
    }

    public function testUpdateReturnNull(): void
    {
        static::assertNull($this->indexer->update(static::createStub(EntityWrittenContainerEvent::class)));
    }

    public function testUpdate(): void
    {
        $streamId = Uuid::randomHex();
        $deletedStreamId = Uuid::randomHex();

        $message = $this->indexer->update(new EntityWrittenContainerEvent(
            Context::createDefaultContext(),
            new NestedEventCollection([
            ]),
            [],
        ));

        static::assertInstanceOf(BlogStreamIndexingMessage::class, $message);
        static::assertSame([$streamId, $deletedStreamId], $message->getData());
    }

    public function testHandle(): void
    {
        $blogStreamId = Uuid::randomHex();
        $filterId1 = Uuid::randomHex();
        $filterId2 = Uuid::randomHex();
        $filterId3 = Uuid::randomHex();

        $filters = [
            [
                'array_key' => $blogStreamId,
                'id' => $filterId1,
                'blog_stream_id' => $blogStreamId,
                'parent_id' => null,
                'type' => 'multi',
                'field' => null,
                'operator' => 'OR',
                'value' => null,
                'parameters' => null,
                'position' => '0',
            ],
            [
                'array_key' => $blogStreamId,
                'id' => $filterId2,
                'entity_stream_id' => $blogStreamId,
                'parent_id' => $filterId1,
                'type' => 'multi',
                'field' => null,
                'operator' => 'AND',
                'value' => null,
                'parameters' => null,
                'position' => '0',
            ],
            [
                'array_key' => $blogStreamId,
                'id' => $filterId3,
                'entity_stream_id' => $blogStreamId,
                'parent_id' => $filterId2,
                'type' => 'not',
                'field' => null,
                'operator' => null,
                'value' => null,
                'parameters' => null,
                'position' => '0',
            ],
            [
                'array_key' => $blogStreamId,
                'id' => Uuid::randomHex(),
                'entity_stream_id' => $blogStreamId,
                'parent_id' => $filterId3,
                'type' => 'equalsAny',
                'field' => 'id',
                'operator' => null,
                'value' => '0189de3825ae719d9a08eeea48d6e13a',
                'parameters' => null,
                'position' => '0',
            ],
        ];

        $query = new MultiFilter(MultiFilter::CONNECTION_OR, [
        ]);
        $serialized = \json_encode([QueryStringParser::toArray($query)]);

        $blogDefinition = $this->createMock(BlogDefinition::class);
        $blogDefinition->expects($this->exactly(8))->method('getEntityName')->willReturn('blog');

        $statement = $this->createMock(Statement::class);
        $params = [
            ['serialized', $serialized],
            ['invalid', 0],
            ['id', Uuid::fromHexToBytes($blogStreamId)],
        ];
        $matcher = $this->exactly(\count($params));
        $statement->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(static function (string $key, $value) use ($matcher, $params): void {
                self::assertSame($params[$matcher->numberOfInvocations() - 1][0], $key);
                self::assertSame($params[$matcher->numberOfInvocations() - 1][1], $value);
            });

        $statement->expects($this->once())->method('executeStatement')->willReturn(1);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociative')->willReturn($filters);
        $connection->expects($this->once())->method('prepare')->willReturn($statement);

        $this->createIndexer(connection: $connection, blogDefinition: $blogDefinition)->handle(new EntityIndexingMessage([$blogStreamId]));
    }

    public function testHandleSkipsEmptyIdFilters(): void
    {
        $blogStreamId = Uuid::randomHex();
        $filterId1 = Uuid::randomHex();
        $filterId2 = Uuid::randomHex();
        $filterId3 = Uuid::randomHex();

        $filters = [
            [
                'array_key' => $blogStreamId,
                'id' => $filterId1,
                'blog_stream_id' => $blogStreamId,
                'parent_id' => null,
                'type' => 'multi',
                'field' => null,
                'operator' => 'OR',
                'value' => null,
                'parameters' => null,
                'position' => '0',
            ],
            [
                'array_key' => $blogStreamId,
                'id' => $filterId2,
                'entity_stream_id' => $blogStreamId,
                'parent_id' => $filterId1,
                'type' => 'multi',
                'field' => null,
                'operator' => 'AND',
                'value' => null,
                'parameters' => null,
                'position' => '0',
            ],
            [
                'array_key' => $blogStreamId,
                'id' => $filterId3,
                'entity_stream_id' => $blogStreamId,
                'parent_id' => $filterId2,
                'type' => 'equals',
                'field' => 'id',
                'operator' => null,
                'value' => null,
                'parameters' => null,
                'position' => '0',
            ],
        ];

        $statement = $this->createMock(Statement::class);
        $params = [
            ['serialized', '[]'],
            ['invalid', 0],
            ['id', Uuid::fromHexToBytes($blogStreamId)],
        ];
        $matcher = $this->exactly(\count($params));
        $statement->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(static function (string $key, $value) use ($matcher, $params): void {
                self::assertSame($params[$matcher->numberOfInvocations() - 1][0], $key);
                self::assertSame($params[$matcher->numberOfInvocations() - 1][1], $value);
            });

        $statement->expects($this->once())->method('executeStatement')->willReturn(1);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())->method('fetchAllAssociative')->willReturn($filters);
        $connection->expects($this->once())->method('prepare')->willReturn($statement);

        $this->createIndexer(connection: $connection)->handle(new EntityIndexingMessage([$blogStreamId]));
    }

    public function testGetTotal(): void
    {
        $result = $this->createMock(Result::class);
        $result->expects($this->once())->method('fetchOne')->willReturn(1);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('getSelectParts')->willReturn(['id']);
        $queryBuilder->expects($this->once())->method('executeQuery')->willReturn($result);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->once())->method('createIterator')->willReturn(new OffsetQuery($queryBuilder));

        $total = $this->createIndexer(iteratorFactory: $iteratorFactory)->getTotal();
        static::assertSame(1, $total);
    }

    private function createIndexer(
        ?Connection $connection = null,
        ?IteratorFactory $iteratorFactory = null,
        ?BlogDefinition $blogDefinition = null,
    ): BlogStreamIndexer {
        return new BlogStreamIndexer(
            $connection ?? $this->connection,
            $iteratorFactory ?? $this->iteratorFactory,
            $this->repository,
            new Serializer([], [new JsonEncoder()]),
            $blogDefinition ?? $this->blogDefinition,
            $this->dispatcher
        );
    }
}
