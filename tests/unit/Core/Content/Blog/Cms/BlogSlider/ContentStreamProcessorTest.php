<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cms\BlogSlider;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Rule\InvocationOrder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Cms\DataResolver\CriteriaCollection;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\FieldConfig;
use Contena\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Contena\Core\Content\Cms\Channel\Struct\BlogSliderStruct;
use Contena\Core\Content\Blog\Cms\BlogSlider\ContentStreamProcessor;
use Contena\Core\Content\Blog\Events\BlogSliderStreamCriteriaEvent;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilter;
use Contena\Core\Content\Blog\Channel\BlogCloseoutFilterFactory;
use Contena\Core\Content\ContentStream\ContentStreamException;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilder;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilderInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Feature\FeatureException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Tax\TaxCollection;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(ContentStreamProcessor::class)]
class ContentStreamProcessorTest extends TestCase
{
    private FieldConfigCollection $config;

    private ContentStreamBuilder&MockObject $contentStreamBuilder;

    /**
     * @var ChannelRepository<BlogCollection>&MockObject
     */
    private ChannelRepository&MockObject $blogRepository;

    private EventDispatcherInterface&MockObject $eventDispatcher;

    private LoggerInterface&MockObject $logger;

    private StaticSystemConfigService $systemConfigService;

    protected function setUp(): void
    {
        $this->configureBlogStreamBuilder();

        $this->blogRepository = $this->createMock(ChannelRepository::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->systemConfigService = new StaticSystemConfigService();
        $this->config = new FieldConfigCollection();
    }

    public function testGetDecorated(): void
    {
        $this->blogRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->expectException(DecorationPatternException::class);
        $this->getProcessor()->getDecorated();
    }

    public function testGetSource(): void
    {
        $this->blogRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        static::assertSame('content_stream', $this->getProcessor()->getSource());
    }

    public function testCollect(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-1');

        $this->config->add($config);

        $this->configureBlogStreamBuilder(enrichCriteriaCalls: $this->once());
        $this->blogRepository->expects($this->never())->method('search');
        $this->logger->expects($this->never())->method('warning');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(BlogSliderStreamCriteriaEvent::class));

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        static::assertCount(1, $list);

        $criteria = $list[BlogDefinition::class]['blog-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);

        $filters = $criteria->getFilters();
        static::assertCount(2, $filters);

        $filter = array_shift($filters);
        static::assertEquals($this->getFilter(), $filter);

        $filter = array_shift($filters);
        $groupingFilter = new NotEqualsFilter('displayGroup', null);

        static::assertEquals($groupingFilter, $filter);

        static::assertTrue($criteria->hasAssociation('options'));
        static::assertTrue($criteria->getAssociation('options')->hasAssociation('group'));
    }

    public function testCollectSkipsGroupingWhenStreamDisplaysVariantsDirectly(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $this->configureBlogStreamBuilder(false, $this->once());

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-1');

        $this->config->add($config);

        $this->blogRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        $criteria = $list[BlogDefinition::class]['blog-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertCount(1, $criteria->getFilters());
        static::assertCount(0, $criteria->getGroupFields());
        static::assertTrue($criteria->hasState(BlogListingLoader::STATE_SKIP_ADD_GROUPING));
    }

    public function testCollectAddsCloseoutFilterToCriteriaWhenHideCloseoutEnabled(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-1');
        $this->config->add($config);

        $this->configureBlogStreamBuilder(enrichCriteriaCalls: $this->once());
        $this->blogRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->enableHideCloseout($resolverContext->getChannelContext()->getChannelId());

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $criteria = $collection->all()[BlogDefinition::class]['blog-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);

        $closeoutFilters = array_filter(
            $criteria->getFilters(),
            static fn ($filter) => $filter instanceof BlogCloseoutFilter
        );
        static::assertCount(
            1,
            $closeoutFilters,
            'Closeout filter must be part of the stream criteria so hidden blogs do not consume the slider limit'
        );
    }

    public function testCollectDoesNotAddCloseoutFilterToCriteriaWhenHideCloseoutDisabled(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-1');
        $this->config->add($config);

        $this->configureBlogStreamBuilder(enrichCriteriaCalls: $this->once());
        $this->blogRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        // $this->systemConfigService is left at its empty default: hide closeout off.

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $criteria = $collection->all()[BlogDefinition::class]['blog-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);

        $closeoutFilters = array_filter(
            $criteria->getFilters(),
            static fn ($filter) => $filter instanceof BlogCloseoutFilter
        );
        static::assertCount(0, $closeoutFilters);
    }

    public function testCollectEventCanModifyCriteria(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-1');
        $this->config->add($config);

        $this->configureBlogStreamBuilder(enrichCriteriaCalls: $this->once());
        $this->blogRepository->expects($this->never())->method('search');
        $this->logger->expects($this->never())->method('warning');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (BlogSliderStreamCriteriaEvent $event): BlogSliderStreamCriteriaEvent {
                $event->criteria->addAssociation('manufacturer');

                return $event;
            });

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        $criteria = $list[BlogDefinition::class]['blog-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertTrue($criteria->hasAssociation('manufacturer'));
    }

    public function testCollectReturnsNullWhenBlogStreamNoLongerExists(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'deleted-blog-stream-id');
        $this->config->add($config);

        $exception = new EntityNotFoundException('content_stream', 'deleted-blog-stream-id');

        $this->blogRepository->expects($this->never())->method('search');

        $this->contentStreamBuilder = $this->createMock(ContentStreamBuilder::class);
        $this->contentStreamBuilder->expects($this->once())
            ->method('enrichCriteria')
            ->with(static::isInstanceOf(Criteria::class), 'deleted-blog-stream-id', $resolverContext->getChannelContext()->getContext())
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Blog stream configured for CMS blog slider could not be found.',
                [
                    'contentStreamId' => 'deleted-blog-stream-id',
                    'exception' => $exception,
                ]
            );

        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        static::assertNull($this->getProcessor()->collect($slot, $this->config, $resolverContext));
    }

    public function testCollectReturnsNullWhenBlogStreamIsBroken(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-id');
        $this->config->add($config);

        $exception = ContentStreamException::noFilters('blog-stream-id');

        $this->blogRepository->expects($this->never())->method('search');

        $this->contentStreamBuilder = $this->createMock(ContentStreamBuilder::class);
        $this->contentStreamBuilder->expects($this->once())
            ->method('enrichCriteria')
            ->with(static::isInstanceOf(Criteria::class), 'blog-stream-id', $resolverContext->getChannelContext()->getContext())
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('warning')
            ->with(
                'Blog stream configured for CMS blog slider has no usable filters.',
                [
                    'contentStreamId' => 'blog-stream-id',
                    'exception' => $exception,
                ]
            );

        $this->eventDispatcher->expects($this->never())
            ->method('dispatch');

        static::assertNull($this->getProcessor()->collect($slot, $this->config, $resolverContext));
    }

    public function testCollectSelectsAllBlogsWhenBlogStreamIsEmpty(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-id');
        $this->config->add($config);

        $this->contentStreamBuilder = $this->createMock(ContentStreamBuilder::class);
        $this->contentStreamBuilder->expects($this->once())
            ->method('enrichCriteria')
            ->willThrowException(ContentStreamException::emptyBlogStream('blog-stream-id'));

        $this->blogRepository->expects($this->never())->method('search');
        $this->logger->expects($this->never())->method('warning');

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(BlogSliderStreamCriteriaEvent::class));

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $criteria = $collection->all()[BlogDefinition::class]['blog-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);
        static::assertEquals([new NotEqualsFilter('displayGroup', null)], $criteria->getFilters());
    }

    public function testCollectDoesNotSwallowDeprecationFromBuildFiltersFallback(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-1');
        $this->config->add($config);

        // An interface-only builder makes the slider fall back to the deprecated buildFilters(), which
        // throws under the v6.8.0.0 flag. That FeatureException must propagate, not be swallowed by the
        // EntityNotFoundException catch around the stream enrichment.
        $exception = FeatureException::error('buildFilters() is removed in v6.8.0.0');

        $this->blogRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $contentStreamBuilder = $this->createMock(ContentStreamBuilderInterface::class);
        $contentStreamBuilder->expects($this->once())
            ->method('buildFilters')
            ->willThrowException($exception);

        $processor = new ContentStreamProcessor(
            $contentStreamBuilder,
            $this->blogRepository,
            $this->eventDispatcher,
            $this->logger,
            $this->systemConfigService,
            new BlogCloseoutFilterFactory(),
        );

        $this->expectExceptionObject($exception);

        $processor->collect($slot, $this->config, $resolverContext);
    }

    public function testCollectAddsRandomSortingIfRequired(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $blogsConfig = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-1');
        $sortingConfig = new FieldConfig('contentStreamSorting', FieldConfig::SOURCE_CONTENT_STREAM, 'random');

        $this->config->add($blogsConfig);
        $this->config->add($sortingConfig);

        $this->configureBlogStreamBuilder(enrichCriteriaCalls: $this->once());
        $this->blogRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->once())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $collection = $this->getProcessor()->collect($slot, $this->config, $resolverContext);
        static::assertInstanceOf(CriteriaCollection::class, $collection);

        $list = $collection->all();
        static::assertCount(1, $list);

        $criteria = $list[BlogDefinition::class]['blog-slider-entity-fallback_id'] ?? null;
        static::assertInstanceOf(Criteria::class, $criteria);

        $sorting = $criteria->getSorting();
        static::assertCount(2, $sorting);
        static::assertContainsOnlyInstancesOf(FieldSorting::class, $sorting);
    }

    public function testEnrich(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-1');
        $this->config->add($config);

        $blogs = BlogSliderFixture::getBlogs();
        $searchResult = BlogSliderFixture::getEntitySearchResult($blogs);

        $data = new ElementDataCollection();
        $data->add('blog-slider-entity-fallback_id', $searchResult);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->blogRepository->expects($this->once())
            ->method('search')->willReturn($searchResult);

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(BlogSliderStruct::class, $slider);
        static::assertSame('blog-stream-1', $slider->getStreamId());
        static::assertSame($blogs, $slider->getBlogs());
    }

    public function testEnrichFiltersOutOfStockCloseoutBlogsWhenHideCloseoutEnabled(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-1');
        $this->config->add($config);

        $blogs = BlogSliderFixture::getBlogs();
        $searchResult = BlogSliderFixture::getEntitySearchResult($blogs);

        $data = new ElementDataCollection();
        $data->add('blog-slider-entity-fallback_id', $searchResult);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->blogRepository->expects($this->once())
            ->method('search')->willReturn($searchResult);

        $this->enableHideCloseout($resolverContext->getChannelContext()->getChannelId());

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(BlogSliderStruct::class, $slider);

        $filteredBlogs = $slider->getBlogs();
        static::assertInstanceOf(BlogCollection::class, $filteredBlogs);
        static::assertCount(1, $filteredBlogs);
        static::assertTrue($filteredBlogs->has('blog-1'));
        static::assertFalse($filteredBlogs->has('blog-2'));
    }

    public function testEnrichKeepsAllBlogsWhenHideCloseoutDisabled(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-1');
        $this->config->add($config);

        $blogs = BlogSliderFixture::getBlogs();
        $searchResult = BlogSliderFixture::getEntitySearchResult($blogs);

        $data = new ElementDataCollection();
        $data->add('blog-slider-entity-fallback_id', $searchResult);

        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->blogRepository->expects($this->once())
            ->method('search')->willReturn($searchResult);

        // $this->systemConfigService is left at its empty default: hide closeout off.

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(BlogSliderStruct::class, $slider);
        static::assertCount(2, $slider->getBlogs() ?? new BlogCollection());
    }

    public function testEnrichDoesNothingWithoutEntitySearchResult(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();
        $data = new ElementDataCollection();

        $this->blogRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->getProcessor()->enrich($slot, $data, $resolverContext);
        static::assertNull($slot->getData());
    }

    public function testEnrichDoesNothingWithoutBlogs(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();
        $data = new ElementDataCollection();

        $result = new EntitySearchResult(
            'tax',
            2,
            new TaxCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $this->blogRepository->expects($this->never())->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $data->add('blog-slider-entity-fallback_id', $result);
        $this->getProcessor()->enrich($slot, $data, $resolverContext);
        static::assertNull($slot->getData());
    }

    public function testEnrichUsesEmptyBlogCollectionIfNoBlogIdsDetermined(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();
        $data = new ElementDataCollection();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-1');
        $this->config->add($config);

        $result = new EntitySearchResult(
            'blog',
            0,
            new BlogCollection(),
            null,
            new Criteria(),
            Context::createDefaultContext()
        );

        $data->add('blog-slider-entity-fallback_id', $result);

        $this->blogRepository->expects($this->never())
            ->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(BlogSliderStruct::class, $slider);
        $blogs = $slider->getBlogs();
        static::assertNotNull($blogs);
        static::assertCount(0, $blogs);
    }

    public function testEnrichKeepsUngroupedVariantsWhenStreamDisplaysVariantsDirectly(): void
    {
        $slot = BlogSliderFixture::getSlot($this->config);
        $resolverContext = BlogSliderFixture::getResolverContext();
        $data = new ElementDataCollection();

        $config = new FieldConfig('blogs', FieldConfig::SOURCE_CONTENT_STREAM, 'blog-stream-1');
        $this->config->add($config);

        $criteria = new Criteria();
        $criteria->addState(BlogListingLoader::STATE_SKIP_ADD_GROUPING);

        $blogs = BlogSliderFixture::getBlogs();
        $result = new EntitySearchResult(
            'blog',
            $blogs->count(),
            $blogs,
            null,
            $criteria,
            Context::createDefaultContext()
        );

        $data->add('blog-slider-entity-fallback_id', $result);

        $this->blogRepository->expects($this->never())
            ->method('search');
        $this->eventDispatcher->expects($this->never())->method('dispatch');
        $this->logger->expects($this->never())->method('warning');

        $this->getProcessor()->enrich($slot, $data, $resolverContext);

        $slider = $slot->getData();
        static::assertInstanceOf(BlogSliderStruct::class, $slider);
        static::assertSame($blogs, $slider->getBlogs());
    }

    /**
     * Scoped to the sales channel so a processor that looked the setting up
     * globally, or under a different key, would still read `false` here.
     */
    private function enableHideCloseout(string $channelId): void
    {
        $this->systemConfigService = new StaticSystemConfigService([
            $channelId => ['core.listing.hideCloseoutBlogsWhenOutOfStock' => true],
        ]);
    }

    private function getProcessor(): ContentStreamProcessor
    {
        return new ContentStreamProcessor(
            $this->contentStreamBuilder,
            $this->blogRepository,
            $this->eventDispatcher,
            $this->logger,
            $this->systemConfigService,
            new BlogCloseoutFilterFactory(),
        );
    }

    private function configureBlogStreamBuilder(bool $displayAsGroup = true, ?InvocationOrder $enrichCriteriaCalls = null): void
    {
        $this->contentStreamBuilder = $this->createMock(ContentStreamBuilder::class);

        $filter = $this->getFilter();

        $this->contentStreamBuilder->expects($enrichCriteriaCalls ?? $this->never())
            ->method('enrichCriteria')
            ->willReturnCallback(static function (Criteria $criteria, mixed ...$_) use ($displayAsGroup, $filter): void {
                $criteria->addFilter($filter);

                if (!$displayAsGroup) {
                    $criteria->addState(BlogListingLoader::STATE_SKIP_ADD_GROUPING);
                }
            });
    }

    private function getFilter(): MultiFilter
    {
        return new MultiFilter(MultiFilter::CONNECTION_OR, [
            new ContainsFilter('blog.name', 'Awesome'),
            new EqualsFilter('blog.id', 'blog-1'),
        ]);
    }
}
