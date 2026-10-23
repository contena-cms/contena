<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\DataAbstractionLayer\ContentStreamMappingIndexingMessage;
use Contena\Core\Content\Blog\DataAbstractionLayer\ContentStreamUpdater;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter\ContentStreamFilterCollection;
use Contena\Core\Content\ContentStream\ContentStreamCollection;
use Contena\Core\Content\ContentStream\ContentStreamEntity;
use Contena\Core\Content\ContentStream\DataAbstractionLayer\ContentStreamIndexer;
use Contena\Core\Content\ContentStream\DataAbstractionLayer\ContentStreamIndexingMessage;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Language\LanguageCollection;
use Contena\Core\System\Language\LanguageEntity;
use Contena\Core\System\Locale\LocaleCollection;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\Messenger\TraceableMessageBus;

/**
 * @internal
 */
class ContentStreamUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<ContentStreamCollection>
     */
    private EntityRepository $contentStreamRepository;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $channelLanguageRepository;

    /**
     * @var EntityRepository<ContentStreamFilterCollection>
     */
    private EntityRepository $contentStreamFilterRepository;

    private ChannelContext $channel;

    private ContentStreamUpdater $contentStreamUpdater;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->contentStreamRepository = static::getContainer()->get('content_stream.repository');
        $this->channelLanguageRepository = static::getContainer()->get('channel_language.repository');
        $this->contentStreamFilterRepository = static::getContainer()->get('content_stream_filter.repository');
        $this->channel = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $this->contentStreamUpdater = static::getContainer()->get(ContentStreamUpdater::class);
    }

    /**
     * @param array<int, array<string, array<string, int>|string>> $filters
     */
    #[DataProvider('filterProvider')]
    public function testIndexingDoesUpdateMappingsAndManyToManyIdField(array $filters): void
    {
        $streamId = Uuid::randomHex();
        $stream = [
            'id' => $streamId,
            'name' => 'test',
            'filters' => $filters,
        ];

        $writtenEvent = $this->contentStreamRepository->create([$stream], Context::createDefaultContext());

        $contentStreamIndexer = static::getContainer()->get(ContentStreamIndexer::class);
        $message = $contentStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(ContentStreamIndexingMessage::class, $message);
        $contentStreamIndexer->handle($message);

        $blogId = Uuid::randomHex();
        $this->createBlog($blogId);

        $message = new ContentStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext());
        $this->contentStreamUpdater->handle($message);

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('streams');
        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);

        $streams = $blog->getStreams();
        static::assertNotNull($streams);
        static::assertCount(1, $streams);
        $firstStream = $streams->first();
        static::assertInstanceOf(ContentStreamEntity::class, $firstStream);
        static::assertSame($streamId, $firstStream->getId());
        static::assertIsArray($blog->getStreamIds());
        static::assertContains($streamId, $blog->getStreamIds());
    }

    /**
     * @return iterable<string, array<int, array<int, array<string, array<string, int>|string>>>>
     */
    public static function filterProvider(): iterable
    {
        yield 'Active filter' => [
            [[
                'type' => 'equals',
                'field' => 'active',
                'value' => '1',
            ]],
        ];

        yield 'Price filter / default price' => [
            [[
                'type' => 'range',
                'field' => 'cheapestPrice',
                'parameters' => [
                    'gte' => 100,
                ],
            ]],
        ];

        yield 'Price filter / advanced price' => [
            [[
                'type' => 'range',
                'field' => 'cheapestPrice',
                'parameters' => [
                    'lte' => 50,
                ],
            ]],
        ];

        yield 'Price filter / default list price percentage' => [
            [[
                'type' => 'range',
                'field' => 'cheapestPrice.percentage',
                'parameters' => [
                    'gte' => 50,
                ],
            ]],
        ];

        yield 'Price filter / advanced list price percentage' => [
            [[
                'type' => 'range',
                'field' => 'cheapestPrice.percentage',
                'parameters' => [
                    'gt' => 50,
                ],
            ]],
        ];
    }

    public function testDeletingAFilterUpdatesTheMapping(): void
    {
        $streamId = Uuid::randomHex();
        $stockFilterId = Uuid::randomHex();

        $this->createStream($streamId, [
            [
                'type' => 'equals',
                'field' => 'active',
                'value' => '1',
            ],
            [
                'id' => $stockFilterId,
                'type' => 'equals',
                'field' => 'stock',
                'value' => '999',
            ],
        ]);

        $blogId = Uuid::randomHex();
        $this->createBlog($blogId);

        $this->contentStreamUpdater->handle(new ContentStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext()));
        $this->assertBlogIsNotInStream($blogId, $streamId);

        $this->clearQueue();

        // the blog only matches once the stock condition is gone
        $deleteEvent = $this->contentStreamFilterRepository->delete([['id' => $stockFilterId]], Context::createDefaultContext());

        $this->recompileStreamFilters($deleteEvent);
        $this->handleDispatchedMappingMessages($streamId);

        $this->assertBlogIsInStream($blogId, $streamId);
    }

    public function testUpdatingAnExistingFilterUpdatesTheMapping(): void
    {
        $streamId = Uuid::randomHex();
        $stockFilterId = Uuid::randomHex();

        $this->createStream($streamId, [
            [
                'id' => $stockFilterId,
                'type' => 'equals',
                'field' => 'stock',
                'value' => '999',
            ],
        ]);

        $blogId = Uuid::randomHex();
        $this->createBlog($blogId);

        $this->contentStreamUpdater->handle(new ContentStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext()));
        $this->assertBlogIsNotInStream($blogId, $streamId);

        $this->clearQueue();

        // only the value changes, so the write payload carries no blog stream id
        $updateEvent = $this->contentStreamFilterRepository->update(
            [['id' => $stockFilterId, 'value' => '1']],
            Context::createDefaultContext()
        );

        $this->recompileStreamFilters($updateEvent);
        $this->handleDispatchedMappingMessages($streamId);

        $this->assertBlogIsInStream($blogId, $streamId);
    }

    /**
     * A reassignment names only the new stream in its payload. The stream losing the filter must be
     * re-indexed too, which is only possible from the previous row state.
     */
    public function testReassigningAFilterUpdatesTheMappingOfBothStreams(): void
    {
        // a stock value that no other fixture uses keeps both streams scoped to these two blogs
        $stock = 4242;
        $matchingBlogId = Uuid::randomHex();
        $otherBlogId = Uuid::randomHex();
        $this->createBlog($matchingBlogId, $stock);
        $this->createBlog($otherBlogId, $stock);

        $oldStreamId = Uuid::randomHex();
        $newStreamId = Uuid::randomHex();
        $filterId = Uuid::randomHex();

        // the blog number condition narrows the old stream down to one of the two blogs
        $this->createStream($oldStreamId, [
            [
                'type' => 'equals',
                'field' => 'stock',
                'value' => (string) $stock,
            ],
            [
                'id' => $filterId,
                'type' => 'equals',
                'field' => 'blogNumber',
                'value' => $matchingBlogId,
            ],
        ]);
        $this->createStream($newStreamId, [
            [
                'type' => 'equals',
                'field' => 'stock',
                'value' => (string) $stock,
            ],
        ]);

        $context = Context::createDefaultContext();
        $this->contentStreamUpdater->handle(new ContentStreamMappingIndexingMessage($oldStreamId, null, $context));
        $this->contentStreamUpdater->handle(new ContentStreamMappingIndexingMessage($newStreamId, null, $context));
        $this->assertBlogIsNotInStream($otherBlogId, $oldStreamId);
        $this->assertBlogIsInStream($otherBlogId, $newStreamId);

        $this->clearQueue();

        $reassignEvent = $this->contentStreamFilterRepository->update(
            [['id' => $filterId, 'contentStreamId' => $newStreamId]],
            $context
        );

        $this->recompileStreamFilters($reassignEvent);
        $this->handleDispatchedMappingMessages($oldStreamId, $newStreamId);

        // the old stream lost its narrowing condition and now matches both blogs
        $this->assertBlogIsInStream($otherBlogId, $oldStreamId);
        // the new stream gained it and no longer matches the second blog
        $this->assertBlogIsNotInStream($otherBlogId, $newStreamId);
    }

    /**
     * A group that lost its last condition must stop matching. The indexer returns no filter rows for
     * it, so it has to be compiled to an empty api_filter rather than keeping its previous one.
     */
    public function testRemovingTheLastFilterClearsTheMapping(): void
    {
        $streamId = Uuid::randomHex();
        $filterId = Uuid::randomHex();

        $blogId = Uuid::randomHex();
        $this->createBlog($blogId);

        $this->createStream($streamId, [
            [
                'id' => $filterId,
                'type' => 'equals',
                'field' => 'blogNumber',
                'value' => $blogId,
            ],
        ]);

        $this->contentStreamUpdater->handle(new ContentStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext()));
        $this->assertBlogIsInStream($blogId, $streamId);

        $this->clearQueue();

        $deleteEvent = $this->contentStreamFilterRepository->delete([['id' => $filterId]], Context::createDefaultContext());

        $this->recompileStreamFilters($deleteEvent);
        $this->handleDispatchedMappingMessages($streamId);

        $this->assertBlogIsNotInStream($blogId, $streamId);
    }

    public function testIndexingDoesNotBreakOnInvalidBlogStreamFilters(): void
    {
        $stream = [
            'name' => 'test',
            'filters' => [[
                'type' => 'equals',
                'field' => 'doesNotExist',
                'value' => '100',
            ]],
        ];

        $writtenEvent = $this->contentStreamRepository->create([$stream], Context::createDefaultContext());

        $contentStreamIndexer = static::getContainer()->get(ContentStreamIndexer::class);
        $message = $contentStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(ContentStreamIndexingMessage::class, $message);
        $contentStreamIndexer->handle($message);

        $blogId = Uuid::randomHex();
        $this->createBlog($blogId);

        // If this call does not throw an exception, everything is ok
        $this->contentStreamUpdater->updateBlogs([$blogId], Context::createDefaultContext());
    }

    public function testConsiderInheritanceVariants(): void
    {
        $activeStreamId = Uuid::randomHex();
        $inActiveStreamId = Uuid::randomHex();

        $writtenEvent = $this->contentStreamRepository->create([
            [
                'id' => $activeStreamId,
                'name' => 'test-inheritance',
                'filters' => [
                    [
                        'type' => 'equals',
                        'field' => 'active',
                        'value' => '1',
                    ],
                ],
            ],
            [
                'id' => $inActiveStreamId,
                'name' => 'test-inheritance',
                'filters' => [
                    [
                        'type' => 'equals',
                        'field' => 'active',
                        'value' => '0',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $contentStreamIndexer = static::getContainer()->get(ContentStreamIndexer::class);
        $update = $contentStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(EntityIndexingMessage::class, $update);

        $contentStreamIndexer->handle($update);

        $blogId = Uuid::randomHex();
        $blogs = [$this->getBlogData($blogId)];

        // Get blog data [variantId => active]
        $variantIds = [
            Uuid::randomHex() => null,
            Uuid::randomHex() => false,
            Uuid::randomHex() => true,
        ];

        foreach ($variantIds as $id => $active) {
            $blogData = $this->getBlogData($id);
            $blogData['parentId'] = $blogId;
            $blogData['active'] = $active;
            $blogs[] = $blogData;
        }

        // Create all (4) blogs at once (fastest)
        $this->blogRepository->create(
            $blogs,
            $this->channel->getContext()
        );

        // Index both active & inactive content_stream
        $this->contentStreamUpdater->handle(new ContentStreamMappingIndexingMessage(
            [$activeStreamId, $inActiveStreamId],
            null,
            $this->channel->getContext()
        ));

        $blogIds = array_keys($variantIds);
        $blogIds[] = $blogId;

        // Valid content_stream for active blogs.
        $activeBlogs = $this->blogRepository->search(
            (new Criteria($blogIds))
                ->addFilter(new EqualsFilter('streams.id', $activeStreamId))
                ->addAssociation('streams'),
            $this->channel->getContext()
        )->getEntities();
        // Check blog & stream count is correct
        static::assertCount(3, $activeBlogs);
        static::assertCount(
            3,
            $activeBlogs->filter(static function (BlogEntity $blog) use ($activeStreamId) {
                $streams = $blog->getStreams();
                if ($streams) {
                    return $streams->filterByProperty('id', $activeStreamId)
                        ->first() !== null;
                }

                return false;
            })
        );
        // Check and ensure the opposite content_stream (inactive) weren't added
        static::assertCount(
            0,
            $activeBlogs->filter(static function (BlogEntity $blog) use ($inActiveStreamId) {
                $streams = $blog->getStreams();
                if ($streams) {
                    return $streams->filterByProperty('id', $inActiveStreamId)
                        ->first() !== null;
                }

                return false;
            })
        );

        // Valid content_stream for inactive blogs.
        $inActiveBlogs = $this->blogRepository->search(
            (new Criteria($blogIds))
                ->addFilter(new EqualsFilter('streams.id', $inActiveStreamId))
                ->addAssociation('streams'),
            $this->channel->getContext()
        )->getEntities();
        // Check blog & stream count is correct
        static::assertCount(1, $inActiveBlogs);
        static::assertCount(
            1,
            $inActiveBlogs->filter(static function (BlogEntity $blog) use ($inActiveStreamId) {
                $streams = $blog->getStreams();
                if ($streams) {
                    return $streams->filterByProperty('id', $inActiveStreamId)
                        ->first() !== null;
                }

                return false;
            })
        );
        // Check and ensure the opposite content_stream (active) weren't added
        static::assertCount(
            0,
            $inActiveBlogs->filter(static function (BlogEntity $blog) use ($activeStreamId) {
                $streams = $blog->getStreams();
                if ($streams) {
                    return $streams->filterByProperty('id', $activeStreamId)
                        ->first() !== null;
                }

                return false;
            })
        );
    }

    public function testBlogStreamIndexingConsidersNonDefaultLanguageCustomFields(): void
    {
        $languageId = $this->createAssignedLanguage('de-DE-' . Uuid::randomHex(), 'Test locale', 'Test', 'Test language');
        $streamId = $this->createCustomFieldStream('Custom field stream');
        $blogId = $this->createTranslatedBlogWithNonDefaultLanguageCustomFieldMatch($languageId);

        $indexMessage = new ContentStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext());
        $this->contentStreamUpdater->handle($indexMessage);

        $this->assertBlogIsInStream($blogId, $streamId);
    }

    public function testUpdateBlogsConsidersNonDefaultLanguageCustomFields(): void
    {
        $languageId = $this->createAssignedLanguage('de-DE-' . Uuid::randomHex(), 'Update locale', 'Update', 'Update language');
        $streamId = $this->createCustomFieldStream('Custom field stream update');
        $blogId = $this->createTranslatedBlogWithNonDefaultLanguageCustomFieldMatch($languageId);

        $this->contentStreamUpdater->updateBlogs([$blogId], Context::createDefaultContext());

        $this->assertBlogIsInStream($blogId, $streamId);
    }

    public function testHandleSkipsBlogsDeletedAfterTheStreamSearch(): void
    {
        $streamId = Uuid::randomHex();
        $this->createStream($streamId, [[
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
        ]]);

        $blogId = Uuid::randomHex();
        $this->createBlog($blogId);

        $deletedBlogId = Uuid::randomHex();
        $this->createBlog($deletedBlogId);
        $this->blogRepository->delete([['id' => $deletedBlogId]], Context::createDefaultContext());

        $updater = $this->createUpdaterWithStaticMatches([$blogId, $deletedBlogId]);

        $updater->handle(new ContentStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext()));

        static::assertSame([$blogId], $this->getMappedBlogIds($streamId));
    }

    public function testUpdateBlogsSkipsBlogsDeletedAfterTheStreamSearch(): void
    {
        $streamId = Uuid::randomHex();
        $this->createStream($streamId, [[
            'type' => 'equals',
            'field' => 'active',
            'value' => '1',
        ]]);

        $blogId = Uuid::randomHex();
        $this->createBlog($blogId);

        $deletedBlogId = Uuid::randomHex();
        $this->createBlog($deletedBlogId);
        $this->blogRepository->delete([['id' => $deletedBlogId]], Context::createDefaultContext());

        $updater = $this->createUpdaterWithStaticMatches([$blogId, $deletedBlogId]);

        $updater->updateBlogs([$blogId, $deletedBlogId], Context::createDefaultContext());

        static::assertSame([$blogId], $this->getMappedBlogIds($streamId));
    }

    /**
     * Returns an updater whose search reports a stale match, as a concurrent delete would.
     *
     * @param list<string> $matches
     */
    private function createUpdaterWithStaticMatches(array $matches): ContentStreamUpdater
    {
        $blogRepository = StaticEntityRepository::of(BlogCollection::class, [], new BlogDefinition());

        // one search runs per stream and language context, so the stale match has to stay available
        $search = new class($matches, $blogRepository) {
            /**
             * @param list<string> $matches
             * @param StaticEntityRepository<BlogCollection> $repository
             */
            public function __construct(
                private readonly array $matches,
                private readonly StaticEntityRepository $repository,
            ) {
            }

            /**
             * @return list<string>
             */
            public function __invoke(): array
            {
                $this->repository->addSearch($this);

                return $this->matches;
            }
        };
        $blogRepository->addSearch($search);

        $language = new LanguageEntity();
        $language->setId(Defaults::LANGUAGE_SYSTEM);
        $languageRepository = StaticEntityRepository::of(LanguageCollection::class, [new LanguageCollection([$language])]);

        return new ContentStreamUpdater(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(BlogDefinition::class),
            $blogRepository,
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get(ManyToManyIdFieldUpdater::class),
            $languageRepository,
            true,
        );
    }

    /**
     * @return list<string>
     */
    private function getMappedBlogIds(string $streamId): array
    {
        return static::getContainer()->get(Connection::class)->fetchFirstColumn(
            'SELECT LOWER(HEX(blog_id)) FROM content_stream_mapping WHERE content_stream_id = :id',
            ['id' => Uuid::fromHexToBytes($streamId)]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     */
    private function createStream(string $streamId, array $filters): void
    {
        $writtenEvent = $this->contentStreamRepository->create([[
            'id' => $streamId,
            'name' => 'test',
            'filters' => $filters,
        ]], Context::createDefaultContext());

        $this->recompileStreamFilters($writtenEvent);
    }

    private function recompileStreamFilters(EntityWrittenContainerEvent $event): void
    {
        $contentStreamIndexer = static::getContainer()->get(ContentStreamIndexer::class);

        $message = $contentStreamIndexer->update($event);
        static::assertInstanceOf(
            ContentStreamIndexingMessage::class,
            $message,
            'expected the write to request a re-compilation of the stream filters'
        );

        $contentStreamIndexer->handle($message);
    }

    private function handleDispatchedMappingMessages(string ...$expectedStreamIds): void
    {
        $bus = static::getContainer()->get('messenger.bus.test_contena');
        static::assertInstanceOf(TraceableMessageBus::class, $bus);

        $handled = [];
        foreach ($bus->getDispatchedMessages() as $dispatched) {
            $message = $dispatched['message'];
            if (!$message instanceof ContentStreamMappingIndexingMessage) {
                continue;
            }

            $handled[] = $message->getData();
            $this->contentStreamUpdater->handle($message);
        }

        foreach ($expectedStreamIds as $expectedStreamId) {
            static::assertContains(
                $expectedStreamId,
                $handled,
                'expected the write to dispatch a mapping update for the affected stream'
            );
        }
    }

    private function assertBlogIsNotInStream(string $blogId, string $streamId): void
    {
        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('streams');
        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertNotContains($streamId, $blog->getStreamIds() ?? []);
    }

    private function createBlog(string $blogId, int $stock = 1): void
    {
        $this->blogRepository->create(
            [
                $this->getBlogData($blogId, $stock),
            ],
            $this->channel->getContext()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getBlogData(string $blogId, int $stock = 1): array
    {
        return [
            'id' => $blogId,
            'blogNumber' => $blogId,
            'stock' => $stock,
            'name' => 'Test',
            'active' => true,
            'type' => BlogDefinition::TYPE_PHYSICAL,
            'price' => [
                [
                    'currencyId' => Defaults::CURRENCY,
                    'gross' => 100,
                    'net' => 9, 'linked' => false,
                    'listPrice' => ['gross' => 200, 'net' => 200, 'linked' => false],
                ],
            ],
            'prices' => [
                [
                    'quantityStart' => 1,
                    'rule' => [
                        'name' => 'Test rule',
                        'priority' => 1,
                    ],
                    'price' => [
                        [
                            'currencyId' => Defaults::CURRENCY,
                            'gross' => 50,
                            'net' => 9, 'linked' => false,
                            'listPrice' => ['gross' => 60, 'net' => 60, 'linked' => false],
                        ],
                    ],
                ],
            ],
            'manufacturer' => ['name' => 'test'],
            'tax' => ['taxRate' => 19, 'name' => 'with id'],
            'visibilities' => [
                ['channelId' => $this->channel->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
            'categories' => [
                ['id' => Uuid::randomHex(), 'name' => 'Clothing'],
            ],
        ];
    }

    private function createCustomFieldStream(string $name): string
    {
        $streamId = Uuid::randomHex();
        $writtenEvent = $this->contentStreamRepository->create([
            [
                'id' => $streamId,
                'name' => $name,
                'filters' => [
                    [
                        'type' => 'equals',
                        'field' => 'customFields.test_stream_checkbox',
                        'value' => 'active',
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        $contentStreamIndexer = static::getContainer()->get(ContentStreamIndexer::class);
        $message = $contentStreamIndexer->update($writtenEvent);

        static::assertInstanceOf(ContentStreamIndexingMessage::class, $message);

        $contentStreamIndexer->handle($message);

        return $streamId;
    }

    private function createTranslatedBlogWithNonDefaultLanguageCustomFieldMatch(string $languageId): string
    {
        $blogId = Uuid::randomHex();

        $this->blogRepository->create([
            [
                'id' => $blogId,
                'blogNumber' => $blogId,
                'stock' => 1,
                'active' => true,
                'price' => [
                    [
                        'currencyId' => Defaults::CURRENCY,
                        'gross' => 100,
                        'net' => 9,
                        'linked' => false,
                    ],
                ],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 19, 'name' => 'with id'],
                'visibilities' => [
                    ['channelId' => $this->channel->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
                'translations' => [
                    [
                        'languageId' => Defaults::LANGUAGE_SYSTEM,
                        'name' => 'Test Blog',
                        'customFields' => null,
                    ],
                    [
                        'languageId' => $languageId,
                        'name' => 'Test Blog Non-Default',
                        'customFields' => ['test_stream_checkbox' => 'active'],
                    ],
                ],
            ],
        ], Context::createDefaultContext());

        return $blogId;
    }

    private function assertBlogIsInStream(string $blogId, string $streamId): void
    {
        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('streams');
        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();

        static::assertInstanceOf(BlogEntity::class, $blog);
        static::assertIsArray($blog->getStreamIds());
        static::assertContains($streamId, $blog->getStreamIds());
    }

    private function createAssignedLanguage(string $localeCode, string $localeName, string $territory, string $languageName): string
    {
        $localeId = Uuid::randomHex();
        $languageId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        /** @var EntityRepository<LocaleCollection> $localeRepository */
        $localeRepository = static::getContainer()->get('locale.repository');
        $localeRepository->create([
            ['id' => $localeId, 'code' => $localeCode, 'name' => $localeName, 'territory' => $territory],
        ], $context);

        /** @var EntityRepository<LanguageCollection> $languageRepository */
        $languageRepository = static::getContainer()->get('language.repository');
        $languageRepository->create([
            [
                'id' => $languageId,
                'name' => $languageName,
                'localeId' => $localeId,
                'translationCodeId' => $localeId,
            ],
        ], $context);

        $this->channelLanguageRepository->create([
            [
                'channelId' => TestDefaults::CHANNEL,
                'languageId' => $languageId,
            ],
        ], $context);

        return $languageId;
    }
}
