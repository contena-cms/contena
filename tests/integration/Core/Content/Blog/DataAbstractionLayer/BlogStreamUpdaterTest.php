<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogStreamMappingIndexingMessage;
use Contena\Core\Content\Blog\DataAbstractionLayer\BlogStreamUpdater;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamFilter\BlogStreamFilterCollection;
use Contena\Core\Content\BlogStream\BlogStreamCollection;
use Contena\Core\Content\BlogStream\BlogStreamEntity;
use Contena\Core\Content\BlogStream\DataAbstractionLayer\BlogStreamIndexer;
use Contena\Core\Content\BlogStream\DataAbstractionLayer\BlogStreamIndexingMessage;
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
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;

/**
 * @internal
 */
class BlogStreamUpdaterTest extends TestCase
{
    use IntegrationTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<BlogStreamCollection>
     */
    private EntityRepository $blogStreamRepository;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $channelLanguageRepository;

    /**
     * @var EntityRepository<BlogStreamFilterCollection>
     */
    private EntityRepository $blogStreamFilterRepository;

    private ChannelContext $channel;

    private BlogStreamUpdater $blogStreamUpdater;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->blogStreamRepository = static::getContainer()->get('blog_stream.repository');
        $this->channelLanguageRepository = static::getContainer()->get('channel_language.repository');
        $this->blogStreamFilterRepository = static::getContainer()->get('blog_stream_filter.repository');
        $this->channel = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);
        $this->blogStreamUpdater = static::getContainer()->get(BlogStreamUpdater::class);
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

        $writtenEvent = $this->blogStreamRepository->create([$stream], Context::createDefaultContext());

        $blogStreamIndexer = static::getContainer()->get(BlogStreamIndexer::class);
        $message = $blogStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(BlogStreamIndexingMessage::class, $message);
        $blogStreamIndexer->handle($message);

        $blogId = Uuid::randomHex();
        $this->createBlog($blogId);

        $message = new BlogStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext());
        $this->blogStreamUpdater->handle($message);

        $criteria = new Criteria([$blogId]);
        $criteria->addAssociation('streams');
        $blog = $this->blogRepository->search($criteria, Context::createDefaultContext())->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blog);

        $streams = $blog->getStreams();
        static::assertNotNull($streams);
        static::assertCount(1, $streams);
        $firstStream = $streams->first();
        static::assertInstanceOf(BlogStreamEntity::class, $firstStream);
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

        $this->blogStreamUpdater->handle(new BlogStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext()));
        $this->assertBlogIsNotInStream($blogId, $streamId);

        $this->clearQueue();

        // the blog only matches once the stock condition is gone
        $deleteEvent = $this->blogStreamFilterRepository->delete([['id' => $stockFilterId]], Context::createDefaultContext());

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

        $this->blogStreamUpdater->handle(new BlogStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext()));
        $this->assertBlogIsNotInStream($blogId, $streamId);

        $this->clearQueue();

        // only the value changes, so the write payload carries no blog stream id
        $updateEvent = $this->blogStreamFilterRepository->update(
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
        $this->blogStreamUpdater->handle(new BlogStreamMappingIndexingMessage($oldStreamId, null, $context));
        $this->blogStreamUpdater->handle(new BlogStreamMappingIndexingMessage($newStreamId, null, $context));
        $this->assertBlogIsNotInStream($otherBlogId, $oldStreamId);
        $this->assertBlogIsInStream($otherBlogId, $newStreamId);

        $this->clearQueue();

        $reassignEvent = $this->blogStreamFilterRepository->update(
            [['id' => $filterId, 'blogStreamId' => $newStreamId]],
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

        $this->blogStreamUpdater->handle(new BlogStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext()));
        $this->assertBlogIsInStream($blogId, $streamId);

        $this->clearQueue();

        $deleteEvent = $this->blogStreamFilterRepository->delete([['id' => $filterId]], Context::createDefaultContext());

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

        $writtenEvent = $this->blogStreamRepository->create([$stream], Context::createDefaultContext());

        $blogStreamIndexer = static::getContainer()->get(BlogStreamIndexer::class);
        $message = $blogStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(BlogStreamIndexingMessage::class, $message);
        $blogStreamIndexer->handle($message);

        $blogId = Uuid::randomHex();
        $this->createBlog($blogId);

        // If this call does not throw an exception, everything is ok
        $this->blogStreamUpdater->updateBlogs([$blogId], Context::createDefaultContext());
    }

    public function testConsiderInheritanceVariants(): void
    {
        $activeStreamId = Uuid::randomHex();
        $inActiveStreamId = Uuid::randomHex();

        $writtenEvent = $this->blogStreamRepository->create([
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

        $blogStreamIndexer = static::getContainer()->get(BlogStreamIndexer::class);
        $update = $blogStreamIndexer->update($writtenEvent);
        static::assertInstanceOf(EntityIndexingMessage::class, $update);

        $blogStreamIndexer->handle($update);

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

        // Index both active & inactive blog_stream
        $this->blogStreamUpdater->handle(new BlogStreamMappingIndexingMessage(
            [$activeStreamId, $inActiveStreamId],
            null,
            $this->channel->getContext()
        ));

        $blogIds = array_keys($variantIds);
        $blogIds[] = $blogId;

        // Valid blog_stream for active blogs.
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
        // Check and ensure the opposite blog_stream (inactive) weren't added
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

        // Valid blog_stream for inactive blogs.
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
        // Check and ensure the opposite blog_stream (active) weren't added
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

        $indexMessage = new BlogStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext());
        $this->blogStreamUpdater->handle($indexMessage);

        $this->assertBlogIsInStream($blogId, $streamId);
    }

    public function testUpdateBlogsConsidersNonDefaultLanguageCustomFields(): void
    {
        $languageId = $this->createAssignedLanguage('de-DE-' . Uuid::randomHex(), 'Update locale', 'Update', 'Update language');
        $streamId = $this->createCustomFieldStream('Custom field stream update');
        $blogId = $this->createTranslatedBlogWithNonDefaultLanguageCustomFieldMatch($languageId);

        $this->blogStreamUpdater->updateBlogs([$blogId], Context::createDefaultContext());

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

        $updater->handle(new BlogStreamMappingIndexingMessage($streamId, null, Context::createDefaultContext()));

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
    private function createUpdaterWithStaticMatches(array $matches): BlogStreamUpdater
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

        return new BlogStreamUpdater(
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
            'SELECT LOWER(HEX(blog_id)) FROM blog_stream_mapping WHERE blog_stream_id = :id',
            ['id' => Uuid::fromHexToBytes($streamId)]
        );
    }

    /**
     * @param array<int, array<string, mixed>> $filters
     */
    private function createStream(string $streamId, array $filters): void
    {
        $writtenEvent = $this->blogStreamRepository->create([[
            'id' => $streamId,
            'name' => 'test',
            'filters' => $filters,
        ]], Context::createDefaultContext());

        $this->recompileStreamFilters($writtenEvent);
    }

    private function recompileStreamFilters(EntityWrittenContainerEvent $event): void
    {
        $blogStreamIndexer = static::getContainer()->get(BlogStreamIndexer::class);

        $message = $blogStreamIndexer->update($event);
        static::assertInstanceOf(
            BlogStreamIndexingMessage::class,
            $message,
            'expected the write to request a re-compilation of the stream filters'
        );

        $blogStreamIndexer->handle($message);
    }

    private function handleDispatchedMappingMessages(string ...$expectedStreamIds): void
    {
        $bus = static::getContainer()->get('messenger.bus.test_contena');
        static::assertInstanceOf(TraceableMessageBus::class, $bus);

        $handled = [];
        foreach ($bus->getDispatchedMessages() as $dispatched) {
            $message = $dispatched['message'];
            if (!$message instanceof BlogStreamMappingIndexingMessage) {
                continue;
            }

            $handled[] = $message->getData();
            $this->blogStreamUpdater->handle($message);
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
        $writtenEvent = $this->blogStreamRepository->create([
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

        $blogStreamIndexer = static::getContainer()->get(BlogStreamIndexer::class);
        $message = $blogStreamIndexer->update($writtenEvent);

        static::assertInstanceOf(BlogStreamIndexingMessage::class, $message);

        $blogStreamIndexer->handle($message);

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
