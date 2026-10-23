<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRouteResponse;
use Contena\Core\Content\Blog\Extension\BlogListingCriteriaExtension;
use Contena\Core\Content\Blog\Extension\BlogListingRouteExtension;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\ContentStream\ContentStreamException;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilder;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilderInterface;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Test\TestCaseHelper\CallableClass;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogListingRoute::class)]
class BlogListingRouteTest extends TestCase
{
    public function testFiltersAreSetForCategories(): void
    {
        $categoryId = 'categoryId';
        $categoryRepository = StaticEntityRepository::of(CategoryCollection::class, [
            new EntityCollection([
                new PartialEntity([
                    'id' => $categoryId,
                    'blogAssignmentType' => CategoryDefinition::BLOG_ASSIGNMENT_TYPE_BLOG,
                ]),
            ]),
        ]);

        $eventDispatcher = new EventDispatcher();
        $controller = new BlogListingRoute(
            static::createStub(BlogListingLoader::class),
            $categoryRepository,
            static::createStub(ContentStreamBuilder::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($eventDispatcher),
            static::createStub(LoggerInterface::class),
        );

        $criteria = new Criteria();
        $controller->load($categoryId, new Request(), static::createStub(ChannelContext::class), $criteria);

        static::assertSame([
            'blog.visibilities.visibility',
            'blog.visibilities.channelId',
            'blog.active',
            'blog.categoriesRo.id',
        ], $criteria->getFilterFields());
    }

    public function testFiltersAreSetForBlogStreams(): void
    {
        $categoryId = 'categoryId';
        $streamId = 'streamId';
        $categoryRepository = StaticEntityRepository::of(CategoryCollection::class, [new EntityCollection([
            new PartialEntity(
                [
                    'id' => $categoryId,
                    'contentStreamId' => $streamId,
                    'blogAssignmentType' => CategoryDefinition::BLOG_ASSIGNMENT_TYPE_CONTENT_STREAM,
                ]
            )])]);

        $contentStreamBuilder = static::createStub(ContentStreamBuilder::class);
        $contentStreamBuilder->method('enrichCriteria')
            ->willReturnCallback(static function (Criteria $criteria, string $id, mixed ...$_): void {
                $criteria->addFilter(new EqualsFilter('blog.content_stream', $id));
            });

        $eventDispatcher = new EventDispatcher();
        $controller = new BlogListingRoute(
            static::createStub(BlogListingLoader::class),
            $categoryRepository,
            $contentStreamBuilder,
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($eventDispatcher),
            static::createStub(LoggerInterface::class),
        );

        $criteria = new Criteria();
        $result = $controller->load(
            $categoryId,
            new Request(),
            static::createStub(ChannelContext::class),
            $criteria
        )->getResult();

        static::assertSame([
            'blog.visibilities.visibility',
            'blog.visibilities.channelId',
            'blog.active',
            'blog.content_stream',
        ], $criteria->getFilterFields());

        static::assertSame($streamId, $result->getStreamId());
    }

    public function testStreamTagIsAddedForBlogStreams(): void
    {
        $categoryId = 'categoryId';
        $streamId = 'streamId';
        $categoryRepository = StaticEntityRepository::of(CategoryCollection::class, [new EntityCollection([
            new PartialEntity(
                [
                    'id' => $categoryId,
                    'contentStreamId' => $streamId,
                    'blogAssignmentType' => CategoryDefinition::BLOG_ASSIGNMENT_TYPE_CONTENT_STREAM,
                ]
            )])]);

        $contentStreamBuilder = static::createStub(ContentStreamBuilder::class);
        $contentStreamBuilder->method('enrichCriteria');

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $calls = [
            [BlogListingRoute::buildName($categoryId)],
            [EntityCacheKeyGenerator::buildStreamTag($streamId)],
        ];
        $matcher = $this->exactly(\count($calls));
        $cacheTagCollector->expects($matcher)
            ->method('addTag')
            ->willReturnCallback(static function (string ...$tags) use ($matcher, $calls): void {
                self::assertSame($calls[$matcher->numberOfInvocations() - 1], $tags);
            });

        $eventDispatcher = new EventDispatcher();
        $controller = new BlogListingRoute(
            static::createStub(BlogListingLoader::class),
            $categoryRepository,
            $contentStreamBuilder,
            $cacheTagCollector,
            new ExtensionDispatcher($eventDispatcher),
            static::createStub(LoggerInterface::class),
        );

        $controller->load(
            $categoryId,
            new Request(),
            static::createStub(ChannelContext::class),
            new Criteria()
        );
    }

    public function testBrokenBlogStreamListsNoBlogsAndLogsAWarning(): void
    {
        $exception = ContentStreamException::noFilters('streamId');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('warning')
            ->with(
                'Blog stream configured for category listing has no usable filters.',
                [
                    'contentStreamId' => 'streamId',
                    'exception' => $exception,
                ]
            );

        $criteria = $this->loadBlogStreamListing($exception, $logger);

        static::assertContainsEquals(new EqualsFilter('blog.id', null), $criteria->getFilters());
    }

    public function testEmptyBlogStreamListsAllBlogs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $criteria = $this->loadBlogStreamListing(ContentStreamException::emptyBlogStream('streamId'), $logger);

        static::assertSame([
            'blog.visibilities.visibility',
            'blog.visibilities.channelId',
            'blog.active',
        ], $criteria->getFilterFields());
    }

    public function testClassIsBaseOfDecorationChain(): void
    {
        $eventDispatcher = new EventDispatcher();
        $controller = new BlogListingRoute(
            static::createStub(BlogListingLoader::class),
            static::createStub(EntityRepository::class),
            static::createStub(ContentStreamBuilder::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($eventDispatcher),
            static::createStub(LoggerInterface::class),
        );

        $this->expectException(DecorationPatternException::class);

        $controller->getDecorated();
    }

    public function testExtension(): void
    {
        $categoryId = 'categoryId';
        $categoryRepository = StaticEntityRepository::of(CategoryCollection::class, [
            new EntityCollection([
                new PartialEntity([
                    'id' => $categoryId,
                    'blogAssignmentType' => CategoryDefinition::BLOG_ASSIGNMENT_TYPE_BLOG,
                ]),
            ]),
        ]);

        $eventDispatcher = new EventDispatcher();

        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->exactly(2))->method('__invoke');
        $eventDispatcher->addListener(BlogListingCriteriaExtension::NAME . '.pre', $listener);
        $eventDispatcher->addListener(BlogListingCriteriaExtension::NAME . '.post', $listener);

        $controller = new BlogListingRoute(
            static::createStub(BlogListingLoader::class),
            $categoryRepository,
            static::createStub(ContentStreamBuilder::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($eventDispatcher),
            static::createStub(LoggerInterface::class),
        );

        $criteria = new Criteria();
        $controller->load($categoryId, new Request(), static::createStub(ChannelContext::class), $criteria);

        static::assertSame([
            'blog.visibilities.visibility',
            'blog.visibilities.channelId',
            'blog.active',
            'blog.categoriesRo.id',
        ], $criteria->getFilterFields());
    }

    public function testBlogStreamWithDisplayAsGroupFalseCanEnableDirectVariantState(): void
    {
        $categoryId = 'categoryId';
        $streamId = 'streamId';

        $categoryRepository = StaticEntityRepository::of(CategoryCollection::class, [new EntityCollection([
            new PartialEntity(
                [
                    'id' => $categoryId,
                    'contentStreamId' => $streamId,
                    'blogAssignmentType' => CategoryDefinition::BLOG_ASSIGNMENT_TYPE_CONTENT_STREAM,
                ]
            )])]);

        $contentStreamBuilder = static::createStub(ContentStreamBuilder::class);
        $contentStreamBuilder->method('enrichCriteria')
            ->willReturnCallback(static function (Criteria $criteria, string $id, mixed ...$_): void {
                $criteria->addFilter(new EqualsFilter('blog.content_stream', $id));
                $criteria->addState(BlogListingLoader::STATE_SKIP_ADD_GROUPING);
            });

        $eventDispatcher = new EventDispatcher();
        $controller = new BlogListingRoute(
            static::createStub(BlogListingLoader::class),
            $categoryRepository,
            $contentStreamBuilder,
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($eventDispatcher),
            static::createStub(LoggerInterface::class),
        );

        $criteria = new Criteria();
        $controller->load(
            $categoryId,
            new Request(),
            static::createStub(ChannelContext::class),
            $criteria
        );

        static::assertTrue($criteria->hasState(BlogListingLoader::STATE_SKIP_ADD_GROUPING));
    }

    public function testBlogStreamFallsBackToBuildFiltersForInterfaceOnlyBuilder(): void
    {
        $categoryId = 'categoryId';
        $streamId = 'streamId';

        $categoryRepository = StaticEntityRepository::of(CategoryCollection::class, [new EntityCollection([
            new PartialEntity(
                [
                    'id' => $categoryId,
                    'contentStreamId' => $streamId,
                    'blogAssignmentType' => CategoryDefinition::BLOG_ASSIGNMENT_TYPE_CONTENT_STREAM,
                ]
            )])]);

        // A builder that only implements the deprecated interface (e.g. a decorator that has not yet
        // adopted AbstractBlogStreamBuilder). The route must fall back to buildFilters() without a
        // TypeError, add the stream filters, and leave display-as-group enabled (no state set).
        $contentStreamBuilder = $this->createMock(ContentStreamBuilderInterface::class);
        $contentStreamBuilder->expects($this->once())
            ->method('buildFilters')
            ->willReturn([new EqualsFilter('blog.content_stream', $streamId)]);

        $eventDispatcher = new EventDispatcher();
        $controller = new BlogListingRoute(
            static::createStub(BlogListingLoader::class),
            $categoryRepository,
            $contentStreamBuilder,
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($eventDispatcher),
            static::createStub(LoggerInterface::class),
        );

        $criteria = new Criteria();
        $controller->load(
            $categoryId,
            new Request(),
            static::createStub(ChannelContext::class),
            $criteria
        );

        static::assertFalse($criteria->hasState(BlogListingLoader::STATE_SKIP_ADD_GROUPING));
        static::assertContainsEquals(new EqualsFilter('blog.content_stream', $streamId), $criteria->getFilters());
    }

    public function testPublishesExtension(): void
    {
        $categoryId = Uuid::randomHex();
        $request = new Request();
        $context = Generator::generateChannelContext();
        $criteria = new Criteria();
        $response = static::createStub(BlogListingRouteResponse::class);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('blog-listing-route.load.pre', static function (BlogListingRouteExtension $extension) use ($categoryId, $request, $context, $criteria, $response): void {
            static::assertSame(['categoryId' => $categoryId, 'request' => $request, 'context' => $context, 'criteria' => $criteria], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new BlogListingRoute(
            static::createStub(BlogListingLoader::class),
            static::createStub(EntityRepository::class),
            static::createStub(ContentStreamBuilder::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
            static::createStub(LoggerInterface::class),
        );

        static::assertSame($response, $route->load($categoryId, $request, $context, $criteria));
    }

    private function loadBlogStreamListing(\Throwable $streamException, LoggerInterface $logger): Criteria
    {
        $categoryRepository = StaticEntityRepository::of(CategoryCollection::class, [new EntityCollection([
            new PartialEntity(
                [
                    'id' => 'categoryId',
                    'contentStreamId' => 'streamId',
                    'blogAssignmentType' => CategoryDefinition::BLOG_ASSIGNMENT_TYPE_CONTENT_STREAM,
                ]
            )])]);

        $contentStreamBuilder = static::createStub(ContentStreamBuilder::class);
        $contentStreamBuilder->method('enrichCriteria')->willThrowException($streamException);

        $route = new BlogListingRoute(
            static::createStub(BlogListingLoader::class),
            $categoryRepository,
            $contentStreamBuilder,
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher(new EventDispatcher()),
            $logger,
        );

        $criteria = new Criteria();
        $route->load('categoryId', new Request(), static::createStub(ChannelContext::class), $criteria);

        return $criteria;
    }
}
