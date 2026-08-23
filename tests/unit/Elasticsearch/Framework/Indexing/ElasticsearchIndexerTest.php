<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework\Indexing;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Elasticsearch\ElasticsearchException;
use Contena\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Contena\Elasticsearch\Framework\ElasticsearchHelper;
use Contena\Elasticsearch\Framework\ElasticsearchRegistry;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexingMessage;
use Contena\Elasticsearch\Framework\Indexing\Event\ElasticsearchIndexIteratorEvent;
use Contena\Elasticsearch\Framework\Indexing\IndexCreator;
use Contena\Elasticsearch\Framework\Indexing\IndexerOffset;
use Contena\Elasticsearch\Framework\Indexing\IndexingDto;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ElasticsearchIndexer::class)]
class ElasticsearchIndexerTest extends TestCase
{
    private Connection&Stub $connection;

    private ElasticsearchHelper&Stub $helper;

    private ElasticsearchRegistry $registry;

    private IndexCreator&Stub $indexCreator;

    private IteratorFactory&Stub $iteratorFactory;

    private Client&Stub $client;

    private IndicesNamespace&Stub $indices;

    private EventDispatcherInterface&Stub $eventDispatcher;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->helper = static::createStub(ElasticsearchHelper::class);
        $this->registry = new ElasticsearchRegistry([$this->createDefinition('blog')]);
        $this->indexCreator = static::createStub(IndexCreator::class);
        $this->iteratorFactory = static::createStub(IteratorFactory::class);
        $this->client = static::createStub(Client::class);
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);

        $this->helper->method('allowIndexing')->willReturn(true);

        $this->indices = static::createStub(IndicesNamespace::class);
        $this->client->method('indices')->willReturn($this->indices);

        parent::setUp();
    }

    public function testIterateESDisabled(): void
    {
        $this->helper = static::createStub(ElasticsearchHelper::class);
        $indexer = $this->getIndexer();

        static::assertNull($indexer->iterate(), 'Iterate should return null if es is disabled');
    }

    public function testIterateTillLastMsgCreatesIndices(): void
    {
        $indexCreator = $this->createMock(IndexCreator::class);
        $indexCreator
            ->expects($this->once())
            ->method('createIndex');

        $indexer = $this->getIndexer(indexCreator: $indexCreator);

        $msg = $indexer->iterate();

        static::assertNull($msg);
    }

    public function testIterateTillLastMsgCreatesIndicesAndIndexTaskInDB(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('elasticsearch_index_task');

        $indexCreator = $this->createMock(IndexCreator::class);
        $indexCreator
            ->method('aliasExists')
            ->willReturn(true);

        $indexCreator
            ->expects($this->once())
            ->method('createIndex');

        $indexer = $this->getIndexer(connection: $connection, indexCreator: $indexCreator);

        $msg = $indexer->iterate();

        static::assertNull($msg);
    }

    public function testIterateWithMessage(): void
    {
        $eventDispatcher = new EventDispatcher();
        $eventDispatched = false;

        $query = static::createStub(IterableQuery::class);
        $query->method('fetch')->willReturn(['1', '2']);

        $this->iteratorFactory
            ->method('createIterator')
            ->willReturn($query);

        $eventDispatcher->addListener(ElasticsearchIndexIteratorEvent::class, static function (ElasticsearchIndexIteratorEvent $event) use (&$eventDispatched, $query): void {
            $eventDispatched = true;
            static::assertSame($query, $event->iterator);
        });

        $indexer = $this->getIndexer(eventDispatcher: $eventDispatcher);

        $offset = new IndexerOffset(['blog'], null);

        $msg = $indexer->iterate($offset);

        static::assertInstanceOf(ElasticsearchIndexingMessage::class, $msg);
        static::assertSame(Defaults::LANGUAGE_SYSTEM, $msg->getContext()->getLanguageId());
        static::assertTrue($msg->getContext()->hasGlobalTenantAccess());
        static::assertSame(['1', '2'], $msg->getData()->getIds());
        static::assertTrue($eventDispatched);
    }

    public function testIterateWithUnknownDefinition(): void
    {
        $indexer = $this->getIndexer();

        $query = static::createStub(IterableQuery::class);
        $query->method('fetch')->willReturn(['1', '2']);

        $this->iteratorFactory
            ->method('createIterator')
            ->willReturn($query);

        $offset = new IndexerOffset(['foo'], null);

        $this->expectExceptionObject(ElasticsearchException::definitionNotFound('foo'));

        $indexer->iterate($offset);
    }

    public function testIterateWithMessageMultipleDefinitions(): void
    {
        $this->registry = new ElasticsearchRegistry([
            $this->createDefinition('blog'),
            $this->createDefinition('category'),
        ]);

        $indexer = $this->getIndexer();

        $msg = $indexer->iterate();

        static::assertNull($msg);
    }

    public function testUpdateIdsESDisabled(): void
    {
        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper
            ->expects($this->never())
            ->method('getIndexName');

        $indexer = $this->getIndexer(helper: $helper);

        $indexer->updateIds(static::createStub(EntityDefinition::class), ['1', '2'], Context::createDefaultContext());
    }

    public function testUpdateIndexDoesNotExistsCreatesThem(): void
    {
        $indexCreator = $this->createMock(IndexCreator::class);
        $indexCreator
            ->expects($this->once())
            ->method('createIndex');

        $indexer = $this->getIndexer(indexCreator: $indexCreator);

        $indexer->updateIds(static::createStub(EntityDefinition::class), ['1', '2'], Context::createDefaultContext());
    }

    public function testHandleESDisabled(): void
    {
        $helper = static::createStub(ElasticsearchHelper::class);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $indexer = $this->getIndexer(connection: $connection, helper: $helper);

        $indexer(new ElasticsearchIndexingMessage(new IndexingDto([Uuid::randomHex()], 'foo', 'not_existing'), null, Context::createDefaultContext()));
    }

    public function testHandleIndexingInvalidDefinition(): void
    {
        $message = new ElasticsearchIndexingMessage(
            new IndexingDto([Uuid::randomHex()], 'foo', 'not_existing'),
            null,
            Context::createDefaultContext()
        );

        $this->indices
            ->method('exists')->willReturn(true);

        $indexer = $this->getIndexer();

        $this->expectExceptionObject(ElasticsearchException::definitionNotFound('not_existing'));

        $indexer($message);
    }

    public function testHandleIndexingNoIds(): void
    {
        $message = new ElasticsearchIndexingMessage(
            new IndexingDto([], 'foo', 'blog'),
            null,
            Context::createDefaultContext()
        );

        $this->indices
            ->method('exists')->willReturn(true);

        $indexer = $this->getIndexer();

        $this->expectExceptionObject(ElasticsearchException::emptyIndexingRequest());

        $indexer($message);
    }

    public function testHandleIndexing(): void
    {
        $blogDefinition = $this->createDefinition('blog');
        $blogDefinition->method('fetch')
            ->willReturn([
                [
                    'id' => '1',
                    'name' => 'foo',
                    'description' => 'bar',
                    'price' => 10,
                    'priority' => 100,
                    'category' => [
                        'id' => '1',
                        'name' => 'foo',
                    ],
                ],
            ]);

        $this->registry = new ElasticsearchRegistry([$blogDefinition]);

        $message = new ElasticsearchIndexingMessage(
            new IndexingDto([Uuid::randomHex()], 'foo', 'blog'),
            null,
            Context::createDefaultContext()
        );

        $this->indices
            ->method('exists')->willReturn(true);

        $client = $this->createMock(Client::class);
        $client->method('indices')->willReturn($this->indices);
        $client->expects($this->once())
            ->method('bulk')
            ->willReturn(['errors' => false, 'items' => []]);

        $indexer = $this->getIndexer(client: $client);

        $indexer($message);
    }

    public function testHandleIndexingFails(): void
    {
        $message = new ElasticsearchIndexingMessage(
            new IndexingDto([Uuid::randomHex()], 'foo', 'blog'),
            null,
            Context::createDefaultContext()
        );

        $this->client->method('bulk')
            ->willReturn([
                'errors' => true,
                'items' => [
                    [
                        'index' => [
                            '_id' => '1',
                            '_index' => 'foo',
                            'status' => 200,
                        ],
                    ],
                    [
                        'index' => [
                            '_id' => '1',
                            '_index' => 'foo',
                            'status' => 400,
                            'error' => [
                                'type' => 'mapper_parsing_exception',
                                'reason' => 'failed to parse',
                            ],
                        ],
                    ],
                ],
            ]);

        $this->indices
            ->method('exists')->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('failed to parse');

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper->method('allowIndexing')->willReturn(true);
        $helper->expects($this->once())->method('logAndThrowException')->with(ElasticsearchException::indexingError([
            [
                'index' => 'foo',
                'id' => '1',
                'type' => 'mapper_parsing_exception',
                'reason' => 'failed to parse',
            ],
        ]));

        $indexer = $this->getIndexer($logger, helper: $helper);

        $indexer($message);
    }

    public function testIterateWithBlogEntity(): void
    {
        $eventDispatched = false;
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(ElasticsearchIndexIteratorEvent::class, static function () use (&$eventDispatched): void {
            $eventDispatched = true;
        });

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('elasticsearch_index_task');

        $this->indexCreator
            ->method('aliasExists')
            ->willReturn(true);

        $indexer = $this->getIndexer(eventDispatcher: $eventDispatcher, connection: $connection);

        $entities = ['blog'];

        $indexer->iterate(null, $entities);
        static::assertTrue($eventDispatched);
    }

    public function testIterateWithBlogAndCategoryEntities(): void
    {
        $this->registry = new ElasticsearchRegistry([
            $this->createDefinition('blog'),
            $this->createDefinition('category'),
        ]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('insert')
            ->with('elasticsearch_index_task');

        $this->indexCreator
            ->method('aliasExists')
            ->willReturn(true);

        $indexer = $this->getIndexer(connection: $connection);

        $entities = ['blog', 'category'];

        $indexer->iterate(null, $entities);
    }

    public function testIterateLogErrorForInvalidEntity(): void
    {
        $logger = static::createStub(LoggerInterface::class);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('insert')
            ->with('elasticsearch_index_task');

        $this->indexCreator
            ->method('aliasExists')
            ->willReturn(true);

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper->method('allowIndexing')->willReturn(true);
        $helper->expects($this->once())->method('logAndThrowException')->with(ElasticsearchException::definitionNotFound('category'));

        $indexer = $this->getIndexer($logger, connection: $connection, helper: $helper);

        $entities = ['blog', 'category'];

        $indexer->iterate(null, $entities);
    }

    private function getIndexer(?LoggerInterface $logger = null, ?EventDispatcherInterface $eventDispatcher = null, ?Connection $connection = null, ?ElasticsearchHelper $helper = null, ?IndexCreator $indexCreator = null, ?Client $client = null): ElasticsearchIndexer
    {
        $logger ??= new NullLogger();
        $eventDispatcher ??= $this->eventDispatcher;
        $connection ??= $this->connection;
        $helper ??= $this->helper;
        $indexCreator ??= $this->indexCreator;
        $client ??= $this->client;

        return new ElasticsearchIndexer(
            $connection,
            $helper,
            $this->registry,
            $indexCreator,
            $this->iteratorFactory,
            $client,
            $logger,
            $eventDispatcher,
            1,
            new NativeClock(),
            true
        );
    }

    /**
     * @return AbstractElasticsearchDefinition&Stub
     */
    private function createDefinition(string $name): AbstractElasticsearchDefinition
    {
        $es = static::createStub(AbstractElasticsearchDefinition::class);

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn($name);

        $es->method('getEntityDefinition')->willReturn($definition);

        return $es;
    }
}
