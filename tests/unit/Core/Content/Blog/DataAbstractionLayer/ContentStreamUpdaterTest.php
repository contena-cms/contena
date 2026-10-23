<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\DataAbstractionLayer\ContentStreamMappingIndexingMessage;
use Contena\Core\Content\Blog\DataAbstractionLayer\ContentStreamUpdater;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(ContentStreamUpdater::class)]
class ContentStreamUpdaterTest extends TestCase
{
    public function testUpdaterCanBeDisabled(): void
    {
        $connectionMock = $this->createMock(Connection::class);
        $connectionMock->expects($this->never())->method(static::anything());

        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects($this->never())->method(static::anything());

        $repo = new StaticEntityRepository([]);

        $languageRepo = new StaticEntityRepository([]);

        $updater = new ContentStreamUpdater(
            $connectionMock,
            new BlogDefinition(),
            $repo,
            $messageBusMock,
            static::createStub(ManyToManyIdFieldUpdater::class),
            $languageRepo,
            false,
        );

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createCLIContext(),
            new NestedEventCollection([
            ]),
            []
        );

        $updater->updateBlogs(['1', '2'], Context::createDefaultContext());
        $updater->update($containerEvent);
    }

    public function testUpdaterWithFilterChange(): void
    {
        $updatedStreamId = Uuid::randomHex();
        $deletedStreamId = Uuid::randomHex();
        $connectionMock = static::createStub(Connection::class);
        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $expectedMessages = [$updatedStreamId, $deletedStreamId];
        $matcher = $this->exactly(\count($expectedMessages));
        $messageBusMock->expects($matcher)->method('dispatch')->willReturnCallback(static function ($message) use ($matcher, $expectedMessages) {
            static::assertInstanceOf(ContentStreamMappingIndexingMessage::class, $message);
            static::assertSame($expectedMessages[$matcher->numberOfInvocations() - 1], $message->getData());
            static::assertSame('content_stream_mapping.indexer', $message->getIndexer());

            return new Envelope($message);
        });

        $repo = new StaticEntityRepository([]);

        $languageRepo = new StaticEntityRepository([]);

        $updater = new ContentStreamUpdater(
            $connectionMock,
            new BlogDefinition(),
            $repo,
            $messageBusMock,
            static::createStub(ManyToManyIdFieldUpdater::class),
            $languageRepo,
            true,
        );

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createCLIContext(),
            new NestedEventCollection([
            ]),
            []
        );

        $updater->update($containerEvent);
    }

    public function testUpdaterWithoutFilterChange(): void
    {
        $connectionMock = static::createStub(Connection::class);

        $messageBusMock = $this->createMock(MessageBusInterface::class);
        $messageBusMock->expects($this->never())->method('dispatch');

        $repo = new StaticEntityRepository([]);

        $languageRepo = new StaticEntityRepository([]);

        $updater = new ContentStreamUpdater(
            $connectionMock,
            new BlogDefinition(),
            $repo,
            $messageBusMock,
            static::createStub(ManyToManyIdFieldUpdater::class),
            $languageRepo,
            true,
        );

        $containerEvent = new EntityWrittenContainerEvent(
            Context::createCLIContext(),
            new NestedEventCollection([
            ]),
            []
        );

        static::assertNull($updater->update($containerEvent));
    }

    /**
     * @param string[] $ids
     * @param array<int, array<string, bool|string>> $filters
     */
    #[DataProvider('filterProvider')]
    public function testCriteriaWithUpdateBlogs(array $ids, array $filters, Criteria $criteria): void
    {
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn($filters);

        $criteria->addFilter(new EqualsAnyFilter('id', $ids));

        /** @var StaticEntityRepository<BlogCollection> */
        $repository = new StaticEntityRepository([
            static function (Criteria $actualCriteria, Context $context) use ($criteria, $ids): array {
                static::assertEquals($criteria, $actualCriteria);

                return $ids;
            },
        ]);

        $updater = new ContentStreamUpdater(
            $connection,
            new BlogDefinition(),
            $repository,
            static::createStub(MessageBusInterface::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            $this->createDefaultLanguageRepo(),
            true,
        );

        $updater->updateBlogs($ids, $context);
    }

    /**
     * @param string[] $ids
     * @param array<int, array<string, bool|string>> $filters
     */
    #[DataProvider('filterProvider')]
    public function testCriteriaWithHandle(array $ids, array $filters, Criteria $criteria): void
    {
        $message = new ContentStreamMappingIndexingMessage(Uuid::randomHex());

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['invalid' => 0, 'api_filter' => current(array_column($filters, 'api_filter'))]);

        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($ids);

        $connection
            ->expects($this->exactly(2))
            ->method('transactional')
            ->withAnyParameters();

        $definition = new BlogDefinition();
        $newMatches = [Uuid::randomHex(), Uuid::randomHex()];
        /** @var StaticEntityRepository<BlogCollection> */
        $repository = new StaticEntityRepository([
            static function (Criteria $actualCriteria, Context $context) use ($criteria, $newMatches): array {
                static::assertTrue($actualCriteria->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
                $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

                static::assertEquals($criteria, $actualCriteria);

                return $newMatches;
            },
            static fn () => [],
        ], $definition);

        $manyToManyFieldUpdater = $this->createMock(ManyToManyIdFieldUpdater::class);

        $ids = [...$newMatches, ...$ids];
        $manyToManyFieldUpdater
            ->expects($this->once())
            ->method('update')
            ->with($definition->getEntityName(), $ids, Context::createDefaultContext(), 'streamIds');

        $updater = new ContentStreamUpdater(
            $connection,
            $definition,
            $repository,
            static::createStub(MessageBusInterface::class),
            $manyToManyFieldUpdater,
            $this->createDefaultLanguageRepo(),
            true,
        );

        $updater->handle($message);
    }

    /**
     * @param string[] $oldMatches
     * @param string[] $newMatches
     * @param string[] $manyToManyUpdatedIds
     */
    #[DataProvider('transactionalProvider')]
    public function testTransactionalHandle(array $oldMatches, array $newMatches, array $manyToManyUpdatedIds, int $numOfTransactional): void
    {
        $message = new ContentStreamMappingIndexingMessage(Uuid::randomHex());

        $filters = json_encode([[
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['invalid' => 0, 'api_filter' => $filters]);

        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($oldMatches);

        $connection
            ->expects($this->exactly($numOfTransactional))
            ->method('transactional')
            ->withAnyParameters();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('blog.active', true));

        $definition = new BlogDefinition();
        /** @var StaticEntityRepository<BlogCollection> */
        $repository = new StaticEntityRepository([
            static function (Criteria $actualCriteria, Context $context) use ($criteria, $newMatches): array {
                static::assertEquals($criteria, $actualCriteria);

                return $newMatches;
            },
            static fn () => [],
        ], $definition);

        $manyToManyFieldUpdater = $this->createMock(ManyToManyIdFieldUpdater::class);

        $manyToManyFieldUpdater
            ->expects($manyToManyUpdatedIds === [] ? $this->never() : $this->once())
            ->method('update')
            ->with($definition->getEntityName(), $manyToManyUpdatedIds, Context::createDefaultContext(), 'streamIds');

        $updater = new ContentStreamUpdater(
            $connection,
            $definition,
            $repository,
            static::createStub(MessageBusInterface::class),
            $manyToManyFieldUpdater,
            $this->createDefaultLanguageRepo(),
            true,
        );

        $updater->handle($message);
    }

    public function testInvalidFilter(): void
    {
        $context = Context::createDefaultContext();

        $message = new ContentStreamMappingIndexingMessage(Uuid::randomHex(), null, $context);

        $filters = json_encode([[
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAssociative')
            ->willReturn(['invalid' => 0, 'api_filter' => $filters]);

        $oldMatches = [Uuid::randomHex(), Uuid::randomHex()];
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn($oldMatches);

        $connection
            ->expects($this->exactly(1)) // delete only
            ->method('transactional')
            ->withAnyParameters();

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('blog.active', true));

        $definition = new BlogDefinition();
        /** @var StaticEntityRepository<BlogCollection> */
        $repository = new StaticEntityRepository([
            static function (Criteria $actualCriteria, Context $context) use ($criteria): array {
                static::assertEquals($criteria, $actualCriteria);

                throw DataAbstractionLayerException::unmappedField('non-existing-field', new BlogDefinition());
            },
            static fn () => [],
        ], $definition);

        $manyToManyFieldUpdater = $this->createMock(ManyToManyIdFieldUpdater::class);

        $manyToManyFieldUpdater
            ->expects($this->once())
            ->method('update')
            ->with($definition->getEntityName(), $oldMatches, $context, 'streamIds');

        $updater = new ContentStreamUpdater(
            $connection,
            $definition,
            $repository,
            static::createStub(MessageBusInterface::class),
            $manyToManyFieldUpdater,
            $this->createDefaultLanguageRepo(),
            true,
        );

        $updater->handle($message);
    }

    public function testUpdateBlogsSkipsInvalidFilter(): void
    {
        $context = Context::createDefaultContext();

        $apiFilter = json_encode([[
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
        ]]);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchAllAssociative')
            ->willReturn([['id' => Uuid::randomBytes(), 'api_filter' => $apiFilter]]);

        // the invalid filter is skipped, so the transaction still runs but inserts nothing
        $connection
            ->expects($this->once())
            ->method('transactional');

        $definition = new BlogDefinition();
        /** @var StaticEntityRepository<BlogCollection> */
        $repository = new StaticEntityRepository([
            static function (): array {
                throw DataAbstractionLayerException::unmappedField('non-existing-field', new BlogDefinition());
            },
        ], $definition);

        $updater = new ContentStreamUpdater(
            $connection,
            $definition,
            $repository,
            static::createStub(MessageBusInterface::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            $this->createDefaultLanguageRepo(),
            true,
        );

        $updater->updateBlogs([Uuid::randomHex()], $context);
    }

    /**
     * @return iterable<string, array<int, array<int, array<string, bool|string>|string>|Criteria>>
     */
    public static function filterProvider(): iterable
    {
        $id = Uuid::randomHex();

        yield 'Active filter' => [
            [$id],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'equals',
                        'field' => 'active',
                        'value' => '1',
                    ]]),
                ],
            ],
            (new Criteria())->addFilter(
                new EqualsFilter('blog.active', true),
            ),
        ];

        yield 'Price filter' => [
            [$id],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'range',
                        'field' => 'blog.cheapestPrice',
                        'parameters' => [
                            'lte' => 50,
                        ],
                    ]]),
                ],
            ],
        ];

        yield 'Nested price filter' => [
            [$id],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'multi',
                        'operator' => 'AND',
                        'queries' => [[
                            'type' => 'range',
                            'field' => 'blog.cheapestPrice',
                            'parameters' => [
                                'lte' => 50,
                            ],
                        ]],
                    ]]),
                ],
            ],
        ];

        yield 'Nested price percentage filter' => [
            [$id],
            [
                [
                    'id' => Uuid::randomHex(),
                    'api_filter' => json_encode([[
                        'type' => 'multi',
                        'operator' => 'AND',
                        'queries' => [[
                            'type' => 'range',
                            'field' => 'cheapestPrice.percentage',
                            'parameters' => [
                                'lte' => 50,
                            ],
                        ]],
                    ]]),
                ],
            ],
        ];
    }

    /**
     * @return iterable<string, array{oldMatches: list<string>, newMatches: list<string>, numOfTransactional: int, manyToManyUpdatedIds: list<string>}>
     */
    public static function transactionalProvider(): iterable
    {
        $blogId1 = Uuid::randomHex();
        $blogId2 = Uuid::randomHex();
        $blogId3 = Uuid::randomHex();
        $blogId4 = Uuid::randomHex();
        $blogId5 = Uuid::randomHex();

        yield 'Both empty old and new matches' => [
            'oldMatches' => [],
            'newMatches' => [],
            'numOfTransactional' => 0, // no change
            'manyToManyUpdatedIds' => [],
        ];

        yield 'Empty old matches' => [
            'oldMatches' => [],
            'newMatches' => [$blogId3, $blogId4, $blogId5],
            'numOfTransactional' => 1, // only add,
            'manyToManyUpdatedIds' => [$blogId3, $blogId4, $blogId5],
        ];

        yield 'Empty new matches' => [
            'oldMatches' => [$blogId1, $blogId2],
            'newMatches' => [],
            'numOfTransactional' => 1, // only delete,
            'manyToManyUpdatedIds' => [$blogId1, $blogId2],
        ];

        yield 'Same old and new matches' => [
            'oldMatches' => [$blogId1, $blogId2],
            'newMatches' => [$blogId1, $blogId2],
            'numOfTransactional' => 0, // no change
            'manyToManyUpdatedIds' => [],
        ];

        yield 'Some old and new matches' => [
            'oldMatches' => [$blogId1, $blogId2],
            'newMatches' => [$blogId2, $blogId3],
            'numOfTransactional' => 2, // add and delete
            'manyToManyUpdatedIds' => [$blogId3, $blogId1],
        ];

        yield 'All different old and new matches' => [
            'oldMatches' => [$blogId1, $blogId2],
            'newMatches' => [$blogId3, $blogId4, $blogId5],
            'numOfTransactional' => 2, // add and delete
            'manyToManyUpdatedIds' => [$blogId3, $blogId4, $blogId5, $blogId1, $blogId2],
        ];
    }

    /**
     * @return StaticEntityRepository<LanguageCollection>
     */
    private function createDefaultLanguageRepo(): StaticEntityRepository
    {
        $language = new LanguageEntity();
        $language->setId(Defaults::LANGUAGE_SYSTEM);

        $repo = new StaticEntityRepository([new LanguageCollection([$language])]);

        return $repo;
    }
}
