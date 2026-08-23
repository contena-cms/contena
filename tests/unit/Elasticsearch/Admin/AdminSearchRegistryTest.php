<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use OpenSearch\Exception\RuntimeException;
use OpenSearch\Namespaces\IndicesNamespace;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Contena\Core\Framework\Api\Context\ChannelApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Event\ProgressAdvancedEvent;
use Contena\Core\Framework\Event\ProgressFinishedEvent;
use Contena\Core\Framework\Event\ProgressStartedEvent;
use Contena\Core\Test\TestDefaults;
use Contena\Elasticsearch\Admin\AdminElasticsearchHelper;
use Contena\Elasticsearch\Admin\AdminIndexingBehavior;
use Contena\Elasticsearch\Admin\AdminSearchIndexingMessage;
use Contena\Elasticsearch\Admin\AdminSearchRegistry;
use Contena\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Contena\Elasticsearch\ElasticsearchException;
use Contena\Elasticsearch\Framework\AbstractElasticsearchDefinition;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(AdminSearchRegistry::class)]
class AdminSearchRegistryTest extends TestCase
{
    private AbstractAdminIndexer&Stub $indexer;

    protected function setUp(): void
    {
        $this->indexer = static::createStub(AbstractAdminIndexer::class);
    }

    public function testGetSubscribedEvents(): void
    {
        $events = AdminSearchRegistry::getSubscribedEvents();

        static::assertArrayHasKey(EntityWrittenContainerEvent::class, $events);
    }

    public function testGetIndexers(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(Client::class),
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );
        $indexers = $registry->getIndexers();

        static::assertSame(['promotion' => $this->indexer], $indexers);
    }

    public function testIndexerLookupIsResolvedOnceFromTheTaggedIterator(): void
    {
        $consumed = 0;
        $indexer = $this->indexer;

        $registry = new AdminSearchRegistry(
            new RewindableGenerator(static function () use (&$consumed, $indexer): \Generator {
                ++$consumed;

                yield 'promotion' => $indexer;
            }, 1),
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(Client::class),
            new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger()),
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        static::assertTrue($registry->hasIndexer('promotion'));
        static::assertSame($this->indexer, $registry->getIndexer('promotion'));
        static::assertTrue($registry->hasIndexer('promotion'));

        static::assertSame(1, $consumed);
    }

    public function testUpdateMapping(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $client = static::createStub(Client::class);

        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->once())
            ->method('putMapping')
            ->with([
                'index' => 'ct-admin-',
                'body' => [],
            ]);

        $client->method('indices')->willReturn($indices);

        $indexer = $this->createMock(AbstractAdminIndexer::class);

        $registry = new AdminSearchRegistry(
            ['promotion' => $indexer],
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        $properties = [
            'id' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'textBoosted' => AbstractAdminIndexer::TEXT_FIELD,
            'text' => AbstractAdminIndexer::TEXT_FIELD,
            'completion' => AbstractAdminIndexer::COMPLETION_FIELD,
            'tenantId' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'entityName' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
            'parameters' => AbstractElasticsearchDefinition::KEYWORD_FIELD,
        ];

        $indexer->expects($this->once())
            ->method('mapping')
            ->with([
                'properties' => $properties,
            ]);
        $registry->updateMappings();
    }

    public function testGetIndexerWithInvalidName(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(Client::class),
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );
        $this->expectException(ElasticsearchException::class);
        $registry->getIndexer('test');
    }

    public function testGetIndexer(): void
    {
        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(Client::class),
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );
        $indexer = $registry->getIndexer('promotion');

        static::assertSame($this->indexer, $indexer);
    }

    public function testIterateWithExistedAliasWillBeSwap(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');

        $client = static::createStub(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->method('existsAlias')->willReturn(true);
        $indices
            ->method('getAlias')
            ->willReturn([
                'ct-admin-promotion-listing_12345' => [
                    'aliases' => [
                        'ct-admin-promotion-listing' => [],
                    ],
                ],
            ]);
        $indices
            ->expects($this->once())
            ->method('delete')
            ->with(['index' => 'ct-admin-promotion-listing_12345']);

        $client->method('indices')->willReturn($indices);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        $registry->iterate(new AdminIndexingBehavior(false));
    }

    public function testIterateSwapsRemainingAliasesWhenAnEarlierOneIsMissing(): void
    {
        $this->indexer->method('getName')->willReturn('blog-listing');
        $this->indexer->method('getEntity')->willReturn('blog');

        $secondIndexer = static::createStub(AbstractAdminIndexer::class);
        $secondIndexer->method('getName')->willReturn('category-listing');
        $secondIndexer->method('getEntity')->willReturn('category');

        $client = static::createStub(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->method('existsAlias')
            ->willReturnCallback(static fn (array $arguments): bool => $arguments['name'] === 'ct-admin-category-listing');
        $indices
            ->method('getAlias')
            ->willReturn([
                'ct-admin-category-listing_12345' => [
                    'aliases' => [
                        'ct-admin-category-listing' => [],
                    ],
                ],
            ]);
        $indices
            ->expects($this->once())
            ->method('delete')
            ->with(['index' => 'ct-admin-category-listing_12345']);

        $client->method('indices')->willReturn($indices);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['blog' => $this->indexer, 'category' => $secondIndexer],
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        $registry->iterate(new AdminIndexingBehavior(false));
    }

    /**
     * @param array{index: array{number_of_shards: int|null, number_of_replicas: int|null, test?: int}} $constructorConfig
     */
    #[DataProvider('providerCreateIndices')]
    public function testIterate(array $constructorConfig): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');

        $client = static::createStub(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->exactly(2))
            ->method('existsAlias')
            ->with(['name' => 'ct-admin-promotion-listing']);

        $client->method('indices')->willReturn($indices);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(['ct-admin-promotion-listing' => 'ct-admin-promotion-listing_12345']);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            ['settings' => $constructorConfig],
            [],
            'test',
            new NativeClock()
        );

        $registry->iterate(new AdminIndexingBehavior(true));
    }

    public function testIterateFiresEvents(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');

        $query = $this->createMock(IterableQuery::class);
        $firstRun = true;

        $query->expects($this->exactly(2))->method('fetch')->willReturnCallback(static function () use (&$firstRun) {
            if ($firstRun) {
                $firstRun = false;

                return ['1', '2'];
            }

            return [];
        });
        $query->method('fetchCount')->willReturn(2);

        $this->indexer->method('getIterator')->willReturn($query);

        $client = static::createStub(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices
            ->expects($this->exactly(2))
            ->method('existsAlias')
            ->with(['name' => 'ct-admin-promotion-listing']);

        $client->method('indices')->willReturn($indices);

        $eventDispatcher = new EventDispatcher();
        $queue = static::createStub(MessageBusInterface::class);
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(['ct-admin-promotion-listing' => 'ct-admin-promotion-listing_12345']);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            $queue,
            $eventDispatcher,
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        $calledStartEvent = false;
        $eventDispatcher->addListener(
            ProgressStartedEvent::class,
            static function (ProgressStartedEvent $event) use (&$calledStartEvent): void {
                $calledStartEvent = true;
                static::assertSame('promotion-listing', $event->getMessage());
                static::assertSame(2, $event->getTotal());
            }
        );

        $calledAdvancedEvent = false;
        $eventDispatcher->addListener(
            ProgressAdvancedEvent::class,
            static function (ProgressAdvancedEvent $event) use (&$calledAdvancedEvent): void {
                $calledAdvancedEvent = true;

                static::assertSame(2, $event->getStep());
            }
        );

        $calledFinishEvent = false;
        $eventDispatcher->addListener(
            ProgressFinishedEvent::class,
            static function (ProgressFinishedEvent $event) use (&$calledFinishEvent): void {
                $calledFinishEvent = true;

                static::assertSame('promotion-listing', $event->getMessage());
            }
        );

        $index->iterate(new AdminIndexingBehavior(true));

        static::assertTrue($calledStartEvent, 'Event ProgressStartedEvent was not dispatched');
        static::assertTrue($calledAdvancedEvent, 'Event ProgressAdvancedEvent was not dispatched');
        static::assertTrue($calledFinishEvent, 'Event ProgressFinishedEvent was not dispatched');
    }

    #[DataProvider('refreshIndicesProvider')]
    public function testRefresh(bool $refreshIndices): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('fetch')->willReturn([
            'c1a28776116d4431a2208eb2960ec340' => [
                'id' => 'c1a28776116d4431a2208eb2960ec340',
                'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
            ],
        ]);
        $this->indexer->method('getUpdatedIds')->willReturn(['c1a28776116d4431a2208eb2960ec340']);

        $client = $this->createMock(Client::class);

        if ($refreshIndices) {
            $indices = $this->createMock(IndicesNamespace::class);
            $indices
                ->expects($this->exactly(2))
                ->method('existsAlias')
                ->with(['name' => 'ct-admin-promotion-listing']);

            $client->method('indices')->willReturn($indices);
        }

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn(['ct-admin-promotion-listing' => 'ct-admin-promotion-listing_12345']);

        $searchHelper = new AdminElasticsearchHelper(true, $refreshIndices, 'ct-admin', 'test', true, new NullLogger());
        $queue = static::createStub(MessageBusInterface::class);

        $client
            ->expects($this->once())
            ->method('bulk')
            ->with([
                'index' => 'ct-admin-promotion-listing_12345',
                'body' => [
                    ['index' => ['_id' => 'c1a28776116d4431a2208eb2960ec340']],
                    [
                        'entityName' => 'promotion',
                        'parameters' => [],
                        'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
                        'textBoosted' => '',
                        'completion' => [],
                        'id' => 'c1a28776116d4431a2208eb2960ec340',
                    ],
                ],
            ]);

        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            $connection,
            $queue,
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        $index->refresh(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
            new EntityWrittenEvent('promotion', [
                new EntityWriteResult(
                    'c1a28776116d4431a2208eb2960ec340',
                    [],
                    'promotion',
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], Context::createDefaultContext()),
        ]), []));
    }

    public function testRefreshQueuesEveryAffectedIndexerForChannelSources(): void
    {
        $this->indexer->method('getName')->willReturn('blog-listing');
        $this->indexer->method('getEntity')->willReturn('blog');
        $this->indexer->method('getUpdatedIds')->willReturn(['c1a28776116d4431a2208eb2960ec340']);

        $secondIndexer = static::createStub(AbstractAdminIndexer::class);
        $secondIndexer->method('getName')->willReturn('category-listing');
        $secondIndexer->method('getEntity')->willReturn('category');
        $secondIndexer->method('getUpdatedIds')->willReturn(['a1a28776116d4431a2208eb2960ec341']);

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([
            'ct-admin-blog-listing' => 'ct-admin-blog-listing_12345',
            'ct-admin-category-listing' => 'ct-admin-category-listing_12345',
        ]);

        $dispatched = [];
        $queue = $this->createMock(MessageBusInterface::class);
        $queue
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use (&$dispatched): Envelope {
                static::assertInstanceOf(AdminSearchIndexingMessage::class, $message);
                $dispatched[] = $message->getEntity();

                return new Envelope($message);
            });

        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $registry = new AdminSearchRegistry(
            ['blog' => $this->indexer, 'category' => $secondIndexer],
            $connection,
            $queue,
            static::createStub(EventDispatcherInterface::class),
            static::createStub(Client::class),
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        $context = Context::createDefaultContext(new ChannelApiSource(TestDefaults::CHANNEL));

        $registry->refresh(new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent('blog', [
                new EntityWriteResult('c1a28776116d4431a2208eb2960ec340', [], 'blog', EntityWriteResult::OPERATION_INSERT),
            ], $context),
            new EntityWrittenEvent('category', [
                new EntityWriteResult('a1a28776116d4431a2208eb2960ec341', [], 'category', EntityWriteResult::OPERATION_INSERT),
            ], $context),
        ]), []));

        static::assertSame(['blog', 'category'], $dispatched);
    }

    public function testInvokeDeletesWhenToRemoveIdsProvided(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('fetch')->willReturn([]); // simulate not found -> should delete

        $client = $this->createMock(Client::class);
        $client
            ->expects($this->once())
            ->method('bulk')
            ->with([
                'index' => 'ct-admin-promotion-listing_12345',
                'body' => [
                    ['delete' => ['_id' => 'deadbeefdeadbeefdeadbeefdeadbeef']],
                ],
            ]);

        $indices = ['ct-admin-promotion-listing' => 'ct-admin-promotion-listing_12345'];

        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        $index->__invoke(new AdminSearchIndexingMessage(
            'promotion',
            'promotion',
            $indices,
            [],
            ['deadbeefdeadbeefdeadbeefdeadbeef']
        ));
    }

    public function testRefreshLogsAndDoesNotIndexIfExceptionIsThrownDuringRefreshIndices(): void
    {
        $indexer = $this->createMock(AbstractAdminIndexer::class);
        $indexer->method('getName')->willReturn('promotion-listing');
        $indexer->method('getEntity')->willReturn('promotion');
        $indexer->expects($this->never())->method('fetch');

        $client = $this->createMock(Client::class);
        $client->expects($this->never())->method('bulk');

        $client->method('indices')->willThrowException(new RuntimeException('no nodes'));

        $connection = static::createStub(Connection::class);

        $searchHelper = new AdminElasticsearchHelper(true, true, 'ct-admin', 'test', true, new NullLogger());
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with('Could not refresh indices. Run "bin/console es:admin:mapping:update" & "bin/console es:admin:index" to update indices and reindex. Error: no nodes');

        $index = new AdminSearchRegistry(
            ['promotion' => $indexer],
            $connection,
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            $logger,
            [],
            [],
            'test',
            new NativeClock()
        );

        $index->refresh(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
            new EntityWrittenEvent('promotion', [
                new EntityWriteResult(
                    'c1a28776116d4431a2208eb2960ec340',
                    [],
                    'promotion',
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], Context::createDefaultContext()),
        ]), []));
    }

    public function testRefreshIndicesNoEmptyDbCall(): void
    {
        $client = static::createStub(Client::class);
        $indices = $this->createMock(IndicesNamespace::class);
        $indices->expects($this->never())->method('existsAlias');

        $client->method('indices')->willReturn($indices);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        $searchHelper = new AdminElasticsearchHelper(true, true, 'ct-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            [],
            $connection,
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        $index->refresh(new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection([
            new EntityWrittenEvent('promotion', [
                new EntityWriteResult(
                    'c1a28776116d4431a2208eb2960ec340',
                    [],
                    'promotion',
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], Context::createDefaultContext()),
        ]), []));
    }

    public function testHandle(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('fetch')->willReturn([
            'c1a28776116d4431a2208eb2960ec340' => [
                'id' => 'c1a28776116d4431a2208eb2960ec340',
                'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
            ],
        ]);

        $client = $this->createMock(Client::class);
        $client
            ->expects($this->once())
            ->method('bulk')
            ->with([
                'index' => 'ct-admin-promotion-listing_12345',
                'body' => [
                    [
                        'index' => [
                            '_id' => 'c1a28776116d4431a2208eb2960ec340',
                        ],
                    ],
                    [
                        'entityName' => 'promotion',
                        'parameters' => [],
                        'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
                        'textBoosted' => '',
                        'completion' => [],
                        'id' => 'c1a28776116d4431a2208eb2960ec340',
                    ],
                ],
            ]);

        $indices = ['ct-admin-promotion-listing' => 'ct-admin-promotion-listing_12345'];

        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        $index->__invoke(new AdminSearchIndexingMessage(
            'promotion',
            'promotion',
            $indices,
            ['c1a28776116d4431a2208eb2960ec340']
        ));
    }

    public function testHandleThrowErrors(): void
    {
        $this->indexer->method('getName')->willReturn('promotion-listing');
        $this->indexer->method('getEntity')->willReturn('promotion');
        $this->indexer->method('fetch')->willReturn([
            'c1a28776116d4431a2208eb2960ec340' => [
                'id' => 'c1a28776116d4431a2208eb2960ec340',
                'text' => 'c1a28776116d4431a2208eb2960ec340 elasticsearch',
            ],
        ]);

        $client = static::createStub(Client::class);
        $result = [
            'took' => 100,
            'errors' => true,
            'items' => [
                [
                    'delete' => [
                        '_index' => 'index1',
                        '_id' => '5',
                        'status' => 404,
                        'error' => [
                            'type' => 'document_missing_exception',
                            'reason' => '[5]: document missing',
                            'index_uuid' => 'aAsFqTI0Tc2W0LCWgPNrOA',
                            'shard' => '0',
                            'index' => 'index1',
                        ],
                    ],
                ],
            ],
        ];
        $client->method('bulk')->willReturn($result);

        $indices = ['ct-admin-promotion-listing' => 'ct-admin-promotion-listing_12345'];

        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $index = new AdminSearchRegistry(
            ['promotion' => $this->indexer],
            static::createStub(Connection::class),
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            $client,
            $searchHelper,
            static::createStub(LoggerInterface::class),
            [],
            [],
            'test',
            new NativeClock()
        );

        $this->expectException(ElasticsearchException::class);
        $index->__invoke(new AdminSearchIndexingMessage(
            'promotion',
            'promotion',
            $indices,
            ['c1a28776116d4431a2208eb2960ec340']
        ));
    }

    /**
     * @return \Generator<array<array{index: array{number_of_shards: int|null, number_of_replicas: int|null, test?: int}}>>
     */
    public static function providerCreateIndices(): \Generator
    {
        yield 'with given number of shards' => [
            [
                'index' => [
                    'number_of_shards' => 1,
                    'number_of_replicas' => 5,
                ],
            ],
        ];

        yield 'with null of shards' => [
            [
                'index' => [
                    'number_of_shards' => null,
                    'number_of_replicas' => null,
                ],
            ],
        ];

        yield 'with null of shards with additional field' => [
            [
                'index' => [
                    'number_of_shards' => null,
                    'number_of_replicas' => null,
                    'test' => 1,
                ],
            ],
        ];
    }

    /**
     * @return iterable<array<bool>>
     */
    public static function refreshIndicesProvider(): iterable
    {
        yield 'refresh indices' => [true];
        yield 'do not refresh indices' => [false];
    }
}
