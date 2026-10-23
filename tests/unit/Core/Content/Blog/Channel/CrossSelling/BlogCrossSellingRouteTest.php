<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\CrossSelling;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingCollection;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingEntity;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsCollection;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs\BlogCrossSellingAssignedBlogsEntity;
use Contena\Core\Content\Blog\Events\BlogCrossSellingStreamCriteriaEvent;
use Contena\Core\Content\Blog\Extension\BlogCrossSellingRouteExtension;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\CrossSelling\CrossSellingElementCollection;
use Contena\Core\Content\Blog\Channel\CrossSelling\BlogCrossSellingRoute;
use Contena\Core\Content\Blog\Channel\CrossSelling\BlogCrossSellingRouteResponse;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\ContentStream\ContentStreamException;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilder;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilderInterface;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(BlogCrossSellingRoute::class)]
class BlogCrossSellingRouteTest extends TestCase
{
    /**
     * @var EntityRepository<BlogCrossSellingCollection>&Stub
     */
    private Stub&EntityRepository $crossSellingRepository;

    private Stub&BlogListingLoader $listingLoader;

    private CacheTagCollector&Stub $cacheTagCollector;

    private ContentStreamBuilder&Stub $contentStreamBuilder;

    private Connection&Stub $connection;

    private BlogCrossSellingRoute $route;

    protected function setUp(): void
    {
        $this->crossSellingRepository = static::createStub(EntityRepository::class);
        $this->listingLoader = static::createStub(BlogListingLoader::class);
        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('fetchOne')->willReturn(false);
        $this->contentStreamBuilder = static::createStub(ContentStreamBuilder::class);
        $this->contentStreamBuilder->method('enrichCriteria')->willReturnCallback(static function (Criteria $criteria, mixed ...$_): void {
            $criteria->addFilter(new EqualsFilter('blog.content_stream', 'stream'));
        });

        $this->route = $this->createRoute();
    }

    public function testLoadAddsTags(): void
    {
        $blogId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $streamId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $childParentId = Uuid::randomHex();

        $crossSelling = new BlogCrossSellingEntity();
        $crossSelling->setUniqueIdentifier($crossSellingId);
        $crossSelling->setType(BlogCrossSellingDefinition::TYPE_CONTENT_STREAM);
        $crossSelling->setBlogStreamId($streamId);
        $crossSelling->setBlogId($blogId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'blog_cross_selling',
                1,
                new BlogCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $child = new BlogEntity();
        $child->setUniqueIdentifier($childId);
        $child->setId($childId);
        $child->setParentId($childParentId);

        $this->listingLoader->method('load')->willReturn(
            new EntitySearchResult(
                'blog',
                1,
                new BlogCollection([$child]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $calls = [
            [EntityCacheKeyGenerator::buildStreamTag($streamId)],
            [
                EntityCacheKeyGenerator::buildBlogTag($blogId),
                EntityCacheKeyGenerator::buildBlogTag($childId),
                EntityCacheKeyGenerator::buildBlogTag($childParentId),
            ],
        ];
        $matcher = $this->exactly(\count($calls));
        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($matcher)
            ->method('addTag')
            ->willReturnCallback(static function (string ...$tags) use ($matcher, $calls): void {
                self::assertSame($calls[$matcher->numberOfInvocations() - 1], $tags);
            });

        $this->createRoute($cacheTagCollector)->load($blogId, new Request(), Generator::generateChannelContext(), new Criteria());
    }

    public function testLoadByStreamPropagatesDirectVariantState(): void
    {
        $blogId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $streamId = Uuid::randomHex();

        $crossSelling = new BlogCrossSellingEntity();
        $crossSelling->setUniqueIdentifier($crossSellingId);
        $crossSelling->setType(BlogCrossSellingDefinition::TYPE_CONTENT_STREAM);
        $crossSelling->setBlogStreamId($streamId);
        $crossSelling->setBlogId($blogId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'blog_cross_selling',
                1,
                new BlogCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $this->contentStreamBuilder->method('enrichCriteria')->willReturnCallback(static function (Criteria $criteria, string $id, mixed ...$_) use ($streamId): void {
            static::assertSame($streamId, $id);
            $criteria->addFilter(new EqualsFilter('blog.content_stream', $streamId));
            $criteria->addState(BlogListingLoader::STATE_SKIP_ADD_GROUPING);
        });

        $listingLoader = $this->createMock(BlogListingLoader::class);
        $listingLoader->expects($this->once())
            ->method('load')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertTrue($criteria->hasState(BlogListingLoader::STATE_SKIP_ADD_GROUPING));

                return new EntitySearchResult(
                    'blog',
                    0,
                    new BlogCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $this->createRoute(listingLoader: $listingLoader)->load($blogId, new Request(), Generator::generateChannelContext(), new Criteria());
    }

    public function testLoadByStreamIgnoresPartialFieldSelection(): void
    {
        $blogId = Uuid::randomHex();
        $crossSellingBlogId = Uuid::randomHex();

        $crossSelling = new BlogCrossSellingEntity();
        $crossSelling->setUniqueIdentifier(Uuid::randomHex());
        $crossSelling->setType(BlogCrossSellingDefinition::TYPE_CONTENT_STREAM);
        $crossSelling->setBlogStreamId(Uuid::randomHex());
        $crossSelling->setBlogId($blogId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'blog_cross_selling',
                1,
                new BlogCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $listingLoader = $this->createMock(BlogListingLoader::class);
        $listingLoader->expects($this->once())
            ->method('load')
            ->willReturnCallback(static fn (Criteria $criteria): EntitySearchResult => self::searchBlogs($criteria, $crossSellingBlogId));

        $element = $this->createRoute(listingLoader: $listingLoader)
            ->load($blogId, new Request(), Generator::generateChannelContext(), (new Criteria())->addFields(['id', 'name']))
            ->getResult()
            ->first();

        static::assertNotNull($element);
        static::assertSame([$crossSellingBlogId], array_values($element->getBlogs()->getIds()));
        static::assertSame(1, $element->getTotal());
    }

    public function testLoadDropsThePartialFieldSelectionBeforeTheCriteriaEvents(): void
    {
        $blogId = Uuid::randomHex();
        $crossSellingBlogId = Uuid::randomHex();

        $crossSelling = new BlogCrossSellingEntity();
        $crossSelling->setUniqueIdentifier(Uuid::randomHex());
        $crossSelling->setType(BlogCrossSellingDefinition::TYPE_CONTENT_STREAM);
        $crossSelling->setBlogStreamId(Uuid::randomHex());
        $crossSelling->setBlogId($blogId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'blog_cross_selling',
                1,
                new BlogCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $listingLoader = $this->createMock(BlogListingLoader::class);
        $listingLoader->expects($this->once())
            ->method('load')
            ->willReturnCallback(static fn (Criteria $criteria): EntitySearchResult => self::searchBlogs($criteria, $crossSellingBlogId));

        // excludeFields() is the documented way to drop heavy columns, but it rejects a criteria that
        // still carries an addFields() selection, so the subscriber must not see one
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addListener(
            BlogCrossSellingStreamCriteriaEvent::class,
            static function (BlogCrossSellingStreamCriteriaEvent $event): void {
                $event->getCriteria()->excludeFields(['description']);
            }
        );

        $route = new BlogCrossSellingRoute(
            $this->crossSellingRepository,
            $eventDispatcher,
            $this->contentStreamBuilder,
            static::createStub(ChannelRepository::class),
            static::createStub(SystemConfigService::class),
            $listingLoader,
            static::createStub(AbstractBlogCloseoutFilterFactory::class),
            $this->cacheTagCollector,
            $this->connection,
            new ExtensionDispatcher(new EventDispatcher()),
            static::createStub(LoggerInterface::class),
        );

        $element = $route
            ->load($blogId, new Request(), Generator::generateChannelContext(), (new Criteria())->addFields(['id', 'name']))
            ->getResult()
            ->first();

        static::assertNotNull($element);
        static::assertSame([$crossSellingBlogId], array_values($element->getBlogs()->getIds()));
    }

    public function testLoadByIdsIgnoresPartialFieldSelection(): void
    {
        $blogId = Uuid::randomHex();
        $crossSellingBlogId = Uuid::randomHex();

        $assignedBlog = new BlogCrossSellingAssignedBlogsEntity();
        $assignedBlog->setUniqueIdentifier(Uuid::randomHex());
        $assignedBlog->setBlogId($crossSellingBlogId);
        $assignedBlog->setPosition(1);

        $crossSelling = new BlogCrossSellingEntity();
        $crossSelling->setUniqueIdentifier(Uuid::randomHex());
        $crossSelling->setType(BlogCrossSellingDefinition::TYPE_BLOG_LIST);
        $crossSelling->setBlogId($blogId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');
        $crossSelling->setAssignedBlogs(new BlogCrossSellingAssignedBlogsCollection([$assignedBlog]));

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'blog_cross_selling',
                1,
                new BlogCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $blogRepository = $this->createMock(ChannelRepository::class);
        $blogRepository->expects($this->once())
            ->method('search')
            ->willReturnCallback(static fn (Criteria $criteria): EntitySearchResult => self::searchBlogs($criteria, $crossSellingBlogId));

        $element = $this->createRoute(blogRepository: $blogRepository)
            ->load($blogId, new Request(), Generator::generateChannelContext(), (new Criteria())->addFields(['id', 'name']))
            ->getResult()
            ->first();

        static::assertNotNull($element);
        static::assertSame([$crossSellingBlogId], array_values($element->getBlogs()->getIds()));
        static::assertSame(1, $element->getTotal());
    }

    public function testLoadAlwaysAddsStreamTagForStreamCrossSelling(): void
    {
        $blogId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $streamId = Uuid::randomHex();

        $crossSelling = new BlogCrossSellingEntity();
        $crossSelling->setUniqueIdentifier($crossSellingId);
        $crossSelling->setType(BlogCrossSellingDefinition::TYPE_CONTENT_STREAM);
        $crossSelling->setBlogStreamId($streamId);
        $crossSelling->setBlogId($blogId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'blog_cross_selling',
                1,
                new BlogCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $this->listingLoader->method('load')->willReturn(
            new EntitySearchResult(
                'blog',
                0,
                new BlogCollection(),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $observedTags = [];
        $this->cacheTagCollector
            ->method('addTag')
            ->willReturnCallback(static function (string ...$tags) use (&$observedTags): void {
                foreach ($tags as $tag) {
                    $observedTags[] = $tag;
                }
            });

        $this->route->load($blogId, new Request(), Generator::generateChannelContext(), new Criteria());

        static::assertContains(
            EntityCacheKeyGenerator::buildStreamTag($streamId),
            $observedTags,
            'Stream tag must be added unconditionally so content_stream_filter writes invalidate cross-selling responses.'
        );
    }

    public function testLoadByStreamExcludesTheCurrentBlog(): void
    {
        $blogId = Uuid::randomHex();

        $criteria = $this->loadStreamCriteria($blogId);

        static::assertContainsEquals(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsFilter('blog.id', $blogId),
                new EqualsFilter('blog.parentId', $blogId),
            ]),
            $criteria->getFilters()
        );
    }

    public function testLoadByStreamExcludesTheCompleteVariantFamilyOfTheCurrentVariant(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $criteria = $this->loadStreamCriteria($variantId, $parentId);

        static::assertContainsEquals(
            new NotFilter(NotFilter::CONNECTION_OR, [
                new EqualsFilter('blog.id', $parentId),
                new EqualsFilter('blog.parentId', $parentId),
            ]),
            $criteria->getFilters(),
            'Variant grouping and main variant resolution would otherwise resolve a sibling variant back to the currently viewed variant.'
        );
    }

    public function testLoadByStreamFallsBackToBuildFiltersForInterfaceOnlyBuilder(): void
    {
        $blogId = Uuid::randomHex();
        $crossSellingId = Uuid::randomHex();
        $streamId = Uuid::randomHex();

        $crossSelling = new BlogCrossSellingEntity();
        $crossSelling->setUniqueIdentifier($crossSellingId);
        $crossSelling->setType(BlogCrossSellingDefinition::TYPE_CONTENT_STREAM);
        $crossSelling->setBlogStreamId($streamId);
        $crossSelling->setBlogId($blogId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'blog_cross_selling',
                1,
                new BlogCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        // A builder that only implements the deprecated interface (e.g. a decorator that has not yet adopted
        // AbstractBlogStreamBuilder). The route must fall back to buildFilters() without a TypeError, add
        // the stream filters and leave display-as-group enabled (no skip-grouping state).
        $contentStreamBuilder = $this->createMock(ContentStreamBuilderInterface::class);
        $contentStreamBuilder->expects($this->once())
            ->method('buildFilters')
            ->willReturn([new EqualsFilter('blog.content_stream', $streamId)]);

        $listingLoader = $this->createMock(BlogListingLoader::class);
        $listingLoader->expects($this->once())
            ->method('load')
            ->willReturnCallback(function (Criteria $criteria) use ($streamId): EntitySearchResult {
                static::assertContainsEquals(new EqualsFilter('blog.content_stream', $streamId), $criteria->getFilters());
                static::assertFalse($criteria->hasState(BlogListingLoader::STATE_SKIP_ADD_GROUPING));

                return new EntitySearchResult(
                    'blog',
                    0,
                    new BlogCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $route = new BlogCrossSellingRoute(
            $this->crossSellingRepository,
            static::createStub(EventDispatcherInterface::class),
            $contentStreamBuilder,
            static::createStub(ChannelRepository::class),
            static::createStub(SystemConfigService::class),
            $listingLoader,
            static::createStub(AbstractBlogCloseoutFilterFactory::class),
            $this->cacheTagCollector,
            $this->connection,
            new ExtensionDispatcher(new EventDispatcher()),
            static::createStub(LoggerInterface::class),
        );

        $route->load($blogId, new Request(), Generator::generateChannelContext(), new Criteria());
    }

    public function testLoadByStreamReturnsAnEmptyElementAndLogsAWarningForABrokenStream(): void
    {
        $blogId = Uuid::randomHex();
        $streamId = Uuid::randomHex();
        $crossSelling = $this->stubStreamCrossSelling($blogId, $streamId);

        $exception = ContentStreamException::noFilters($streamId);
        $contentStreamBuilder = static::createStub(ContentStreamBuilder::class);
        $contentStreamBuilder->method('enrichCriteria')->willThrowException($exception);

        $listingLoader = $this->createMock(BlogListingLoader::class);
        $listingLoader->expects($this->never())->method('load');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Blog stream configured for cross-selling has no usable filters.',
                [
                    'contentStreamId' => $streamId,
                    'exception' => $exception,
                ]
            );

        $observedTags = [];
        $this->cacheTagCollector
            ->method('addTag')
            ->willReturnCallback(static function (string ...$tags) use (&$observedTags): void {
                foreach ($tags as $tag) {
                    $observedTags[] = $tag;
                }
            });

        $element = $this->createRoute(listingLoader: $listingLoader, contentStreamBuilder: $contentStreamBuilder, logger: $logger)
            ->load($blogId, new Request(), Generator::generateChannelContext(), new Criteria())
            ->getResult()
            ->first();

        static::assertNotNull($element);
        static::assertSame($crossSelling, $element->getCrossSelling());
        static::assertSame($streamId, $element->getStreamId());
        static::assertCount(0, $element->getBlogs());
        static::assertSame(0, $element->getTotal());
        static::assertContains(
            EntityCacheKeyGenerator::buildStreamTag($streamId),
            $observedTags,
            'The cached response must be invalidated once the stream gets usable filters.'
        );
    }

    public function testLoadByStreamSelectsAllBlogsForAnEmptyStream(): void
    {
        $blogId = Uuid::randomHex();
        $streamId = Uuid::randomHex();
        $this->stubStreamCrossSelling($blogId, $streamId);

        $contentStreamBuilder = static::createStub(ContentStreamBuilder::class);
        $contentStreamBuilder->method('enrichCriteria')->willThrowException(ContentStreamException::emptyBlogStream($streamId));

        $crossSellingBlog = new BlogEntity();
        $crossSellingBlog->setId(Uuid::randomHex());
        $crossSellingBlog->setUniqueIdentifier($crossSellingBlog->getId());

        $listingLoader = $this->createMock(BlogListingLoader::class);
        $listingLoader->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Criteria $criteria) use ($blogId, $crossSellingBlog): EntitySearchResult {
                static::assertEquals(
                    [
                        new NotFilter(NotFilter::CONNECTION_OR, [
                            new EqualsFilter('blog.id', $blogId),
                            new EqualsFilter('blog.parentId', $blogId),
                        ]),
                    ],
                    $criteria->getFilters()
                );

                return new EntitySearchResult(
                    'blog',
                    1,
                    new BlogCollection([$crossSellingBlog]),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $element = $this->createRoute(listingLoader: $listingLoader, contentStreamBuilder: $contentStreamBuilder, logger: $logger)
            ->load($blogId, new Request(), Generator::generateChannelContext(), new Criteria())
            ->getResult()
            ->first();

        static::assertNotNull($element);
        static::assertSame([$crossSellingBlog], array_values($element->getBlogs()->getElements()));
        static::assertSame(1, $element->getTotal());
    }

    public function testPublishesExtension(): void
    {
        $blogId = Uuid::randomHex();
        $request = new Request();
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();
        $response = new BlogCrossSellingRouteResponse(new CrossSellingElementCollection());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('blog-cross-selling-route.load.pre', static function (BlogCrossSellingRouteExtension $extension) use ($blogId, $request, $context, $criteria, $response): void {
            static::assertSame(['blogId' => $blogId, 'request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new BlogCrossSellingRoute(
            static::createStub(EntityRepository::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(ContentStreamBuilder::class),
            static::createStub(ChannelRepository::class),
            static::createStub(SystemConfigService::class),
            static::createStub(BlogListingLoader::class),
            static::createStub(AbstractBlogCloseoutFilterFactory::class),
            static::createStub(CacheTagCollector::class),
            static::createStub(Connection::class),
            new ExtensionDispatcher($dispatcher),
            static::createStub(LoggerInterface::class),
        );

        static::assertSame($response, $route->load($blogId, $request, $context, $criteria));
    }

    /**
     * Loads a single stream cross-selling of the blog and returns the criteria it was loaded with.
     *
     * @param string|null $parentId parent of the loaded blog, which also owns the cross-selling because
     *                              variants inherit the cross-sellings of their parent
     */
    private function loadStreamCriteria(string $blogId, ?string $parentId = null): Criteria
    {
        $crossSelling = new BlogCrossSellingEntity();
        $crossSelling->setUniqueIdentifier(Uuid::randomHex());
        $crossSelling->setType(BlogCrossSellingDefinition::TYPE_CONTENT_STREAM);
        $crossSelling->setBlogStreamId(Uuid::randomHex());
        $crossSelling->setBlogId($parentId ?? $blogId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'blog_cross_selling',
                1,
                new BlogCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $connection = static::createStub(Connection::class);
        $connection->method('fetchOne')->willReturn($parentId ?? false);

        $streamCriteria = null;
        $listingLoader = $this->createMock(BlogListingLoader::class);
        $listingLoader->expects($this->once())
            ->method('load')
            ->willReturnCallback(static function (Criteria $criteria) use (&$streamCriteria): EntitySearchResult {
                $streamCriteria = $criteria;

                return new EntitySearchResult(
                    'blog',
                    0,
                    new BlogCollection(),
                    null,
                    $criteria,
                    Context::createDefaultContext()
                );
            });

        $this->createRoute(listingLoader: $listingLoader, connection: $connection)
            ->load($blogId, new Request(), Generator::generateChannelContext(), new Criteria());

        static::assertInstanceOf(Criteria::class, $streamCriteria);

        return $streamCriteria;
    }

    /**
     * Emulates the reader: an allowlist field selection hydrates PartialEntity instances into a generic
     * EntityCollection, which the cross selling elements are not typed against.
     *
     * @return EntitySearchResult<covariant EntityCollection<Entity>>
     */
    private static function searchBlogs(Criteria $criteria, string $blogId): EntitySearchResult
    {
        if ($criteria->getFields() !== []) {
            $partial = new PartialEntity();
            $partial->setUniqueIdentifier($blogId);
            $partial->assign(['id' => $blogId]);
            $partial->internalSetEntityData(BlogDefinition::ENTITY_NAME, new FieldVisibility([]));

            $blogs = new EntityCollection([$partial]);
        } else {
            $blog = new BlogEntity();
            $blog->setUniqueIdentifier($blogId);
            $blog->setId($blogId);

            $blogs = new BlogCollection([$blog]);
        }

        return new EntitySearchResult(
            'blog',
            1,
            $blogs,
            null,
            $criteria,
            Context::createDefaultContext()
        );
    }

    /**
     * @param ChannelRepository<BlogCollection>|null $blogRepository
     */
    private function createRoute(
        ?CacheTagCollector $cacheTagCollector = null,
        ?BlogListingLoader $listingLoader = null,
        ?ChannelRepository $blogRepository = null,
        ?Connection $connection = null,
        ?ContentStreamBuilder $contentStreamBuilder = null,
        ?LoggerInterface $logger = null,
    ): BlogCrossSellingRoute {
        return new BlogCrossSellingRoute(
            $this->crossSellingRepository,
            static::createStub(EventDispatcherInterface::class),
            $contentStreamBuilder ?? $this->contentStreamBuilder,
            $blogRepository ?? static::createStub(ChannelRepository::class),
            static::createStub(SystemConfigService::class),
            $listingLoader ?? $this->listingLoader,
            static::createStub(AbstractBlogCloseoutFilterFactory::class),
            $cacheTagCollector ?? $this->cacheTagCollector,
            $connection ?? $this->connection,
            new ExtensionDispatcher(new EventDispatcher()),
            $logger ?? static::createStub(LoggerInterface::class),
        );
    }

    private function stubStreamCrossSelling(string $blogId, string $streamId): BlogCrossSellingEntity
    {
        $crossSelling = new BlogCrossSellingEntity();
        $crossSelling->setUniqueIdentifier(Uuid::randomHex());
        $crossSelling->setType(BlogCrossSellingDefinition::TYPE_CONTENT_STREAM);
        $crossSelling->setBlogStreamId($streamId);
        $crossSelling->setBlogId($blogId);
        $crossSelling->setLimit(10);
        $crossSelling->setSortBy('name');
        $crossSelling->setSortDirection('ASC');

        $this->crossSellingRepository->method('search')->willReturn(
            new EntitySearchResult(
                'blog_cross_selling',
                1,
                new BlogCrossSellingCollection([$crossSelling]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        return $crossSelling;
    }
}
