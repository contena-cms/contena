<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Page\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewEntity;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRouteResponse;
use Contena\Core\Content\Blog\Cms\BlogDescriptionReviewsCmsElementResolver;
use Contena\Core\Content\Blog\Cms\CrossSellingCmsElementResolver;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockCollection;
use Contena\Core\Content\Cms\Aggregate\CmsBlock\CmsBlockEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSection\CmsSectionEntity;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotCollection;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogDescriptionReviewsStruct;
use Contena\Core\Content\Cms\Channel\Struct\CrossSellingStruct;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Annotation\DisabledFeatures;
use Contena\Core\Test\Generator;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Contena\Frontend\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BlogPageLoader::class)]
class BlogPageLoaderTest extends TestCase
{
    public function testItLoadsReviews(): void
    {
        $blogId = Uuid::randomHex();
        $request = new Request([], [], ['blogId' => $blogId]);
        $channelContext = $this->getChannelContext();
        $reviews = $this->getCmsSlotConfig();

        $blogPageLoader = $this->getBlogPageLoaderWithBlog($blogId, $reviews);

        $page = $blogPageLoader->load($request, $channelContext);

        $slot = $page->getCmsPage()?->getSections()?->first()?->getBlocks()?->first()?->getSlots()?->first()?->getSlot();
        static::assertIsString($slot);

        static::assertSame($reviews, json_decode($slot, true, 512, \JSON_THROW_ON_ERROR));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the BREADCRUMB_REWORK flag
     */
    #[DisabledFeatures(['BREADCRUMB_REWORK'])]
    public function testExplicitBreadcrumbOptOutWinsWithMajorActive(): void
    {
        $blogId = Uuid::randomHex();
        $request = new Request([], [], ['blogId' => $blogId]);
        $context = $this->getChannelContext();
        $category = new CategoryEntity();
        $category->setId(Uuid::randomHex());

        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $breadcrumbBuilder->expects($this->never())->method('getCategoryBreadcrumbUrls');

        $page = $this->getBlogPageLoaderWithBlog(
            $blogId,
            $this->getCmsSlotConfig(),
            seoCategory: $category,
            breadcrumbBuilder: $breadcrumbBuilder,
        )->load($request, $context);

        static::assertNull($page->getBreadcrumb());
    }

    public function testItLoadsStructuredDataReviewsForJsonLd(): void
    {
        $blogId = Uuid::randomHex();
        $request = new Request([], [], ['blogId' => $blogId]);
        $channelContext = $this->getChannelContext();

        $review = new BlogReviewEntity();
        $review->setId(Uuid::randomHex());
        $review->setTitle('Great blog');
        $review->setContent('Really changed my life');
        $review->setPoints(5);
        $review->setStatus(true);

        $reviewCollection = new BlogReviewCollection([$review]);

        $entityResult = new EntitySearchResult(
            BlogReviewDefinition::ENTITY_NAME,
            1,
            $reviewCollection,
            new AggregationResultCollection([
                new TermsResult('ratingMatrix', []),
            ]),
            new Criteria(),
            Context::createDefaultContext()
        );

        $reviewRepositoryMock = $this->createMock(EntityRepository::class);
        $reviewRepositoryMock
            ->expects(Feature::isActive('JSON_LD_DATA') ? $this->once() : $this->never())
            ->method('search')
            ->willReturn($entityResult);

        $blogPageLoader = $this->getBlogPageLoaderWithBlog(
            $blogId,
            $this->getCmsSlotConfig(),
            $reviewRepositoryMock
        );

        $page = $blogPageLoader->load($request, $channelContext);

        if (Feature::isActive('JSON_LD_DATA')) {
            $reviewData = $page->getStructuredDataReviews();
            static::assertNotNull($reviewData);
            static::assertSame(1, $reviewData->getTotal());
            static::assertSame($blogId, $reviewData->getBlogId());

            $loadedReview = $reviewData->getEntities()->first();
            static::assertNotNull($loadedReview);
            static::assertSame('Great blog', $loadedReview->getTitle());
        } else {
            static::assertNull($page->getStructuredDataReviews());
        }
    }

    public function testItSkipsStructuredDataReviewsWhenReviewsAreDisabled(): void
    {
        $blogId = Uuid::randomHex();
        $request = new Request([], [], ['blogId' => $blogId]);
        $channelContext = $this->getChannelContext();

        $reviewRepositoryMock = $this->createMock(EntityRepository::class);
        $reviewRepositoryMock->expects($this->never())->method('search');

        $systemConfigMock = static::createStub(SystemConfigService::class);
        $systemConfigMock->method('getBool')->willReturn(false);

        $blogPageLoader = $this->getBlogPageLoaderWithBlog(
            $blogId,
            $this->getCmsSlotConfig(),
            $reviewRepositoryMock,
            $systemConfigMock,
        );

        $page = $blogPageLoader->load($request, $channelContext);

        static::assertNull($page->getStructuredDataReviews());
    }

    public function testItSetsEmptyStructuredDataReviewsWhenNoReviewsExist(): void
    {
        $blogId = Uuid::randomHex();
        $request = new Request([], [], ['blogId' => $blogId]);
        $channelContext = $this->getChannelContext();

        $entityResult = new EntitySearchResult(
            BlogReviewDefinition::ENTITY_NAME,
            0,
            new AggregationResultCollection([
                new TermsResult('ratingMatrix', []),
            ]),
            new Criteria(),
            Context::createDefaultContext()
        );

        $reviewRepositoryMock = $this->createMock(EntityRepository::class);
        $reviewRepositoryMock
            ->expects(Feature::isActive('JSON_LD_DATA') ? $this->once() : $this->never())
            ->method('search')
            ->willReturn($entityResult);

        $blogPageLoader = $this->getBlogPageLoaderWithBlog(
            $blogId,
            $this->getCmsSlotConfig(),
            $reviewRepositoryMock
        );

        $page = $blogPageLoader->load($request, $channelContext);

        if (Feature::isActive('JSON_LD_DATA')) {
            $reviewData = $page->getStructuredDataReviews();
            static::assertNotNull($reviewData);
            static::assertSame(0, $reviewData->getTotal());
            static::assertSame(0, $reviewData->getMatrix()->getTotalReviewCount());
        } else {
            static::assertNull($page->getStructuredDataReviews());
        }
    }

    /**
     * @param array<string, array<string, array<string, array<string, array<string, string>>>>> $reviews
     * @param EntityRepository<BlogReviewCollection>|null $reviewRepository
     */
    private function getBlogPageLoaderWithBlog(
        string $blogId,
        array $reviews,
        ?EntityRepository $reviewRepository = null,
        ?SystemConfigService $systemConfigService = null,
        ?CategoryEntity $seoCategory = null,
        ?CategoryBreadcrumbBuilder $breadcrumbBuilder = null,
    ): BlogPageLoader {
        $blog = $this->getBlogWithReviews($blogId, $reviews);

        if ($seoCategory !== null) {
            $blog->setSeoCategory($seoCategory);
        }

        // set cms page which later will be set by the subscriber
        $blog->setCmsPage($this->getCmsPage($blog));
        $blog->setBlogNumber($blogId);

        $criteria = (new Criteria())
            ->addAssociation('manufacturer.media')
            ->addAssociation('options.group')
            ->addAssociation('properties.group')
            ->addAssociation('mainCategories.category')
            ->addAssociation('media.media')
            ->addAssociation('openGraphMedia');

        $criteria->getAssociation('media')->addSorting(
            new FieldSorting('position')
        );

        $blogDetailRouteMock = static::createStub(BlogDetailRoute::class);
        $blogDetailRouteMock
            ->method('load')
            ->willReturn(new BlogDetailRouteResponse($blog, null));

        if ($reviewRepository === null) {
            $entityResult = new EntitySearchResult(
                BlogReviewDefinition::ENTITY_NAME,
                0,
                new AggregationResultCollection([new TermsResult('ratingMatrix', [])]),
                new Criteria(),
                Context::createDefaultContext()
            );
            $reviewRepository = $this->createMock(EntityRepository::class);
            $reviewRepository
                ->expects(Feature::isActive('JSON_LD_DATA') ? $this->once() : $this->never())
                ->method('search')
                ->willReturn($entityResult);
        }

        if ($systemConfigService === null) {
            $systemConfigService = static::createStub(SystemConfigService::class);
            // Default: reviews are enabled so the repository is actually called.
            $systemConfigService->method('getBool')->willReturn(true);
        }

        return new BlogPageLoader(
            static::createStub(GenericPageLoader::class),
            static::createStub(EventDispatcherInterface::class),
            $blogDetailRouteMock,
            $reviewRepository,
            $systemConfigService,
            $breadcrumbBuilder ?? static::createStub(CategoryBreadcrumbBuilder::class)
        );
    }

    /**
     * @param array<string, array<string, array<string, array<string, array<string, string>>>>> $reviews
     */
    private function getBlogWithReviews(string $blogId, array $reviews): ChannelBlogEntity
    {
        $blog = new ChannelBlogEntity();
        $blog->setId($blogId);

        // set reviews
        $blog->setTranslated($reviews);

        return $blog;
    }

    private function getChannelContext(): ChannelContext
    {
        $channelEntity = new ChannelEntity();
        $channelEntity->setId('channelId');

        return Generator::generateChannelContext(
            channel: $channelEntity,
        );
    }

    private function getCmsPage(ChannelBlogEntity $blogEntity): CmsPageEntity
    {
        $reviewBlock = $this->getReviewBlock($blogEntity);
        $crossSellingBlock = $this->getCrossSellingBlock();

        $firstCmsSectionEntity = new CmsSectionEntity();
        $firstCmsSectionEntity->setId(Uuid::randomHex());
        $firstCmsSectionEntity->setBlocks(new CmsBlockCollection([$reviewBlock]));

        $secondCmsSectionEntity = new CmsSectionEntity();
        $secondCmsSectionEntity->setId(Uuid::randomHex());
        $secondCmsSectionEntity->setBlocks(new CmsBlockCollection([$crossSellingBlock]));

        $cmsPageEntity = new CmsPageEntity();
        $cmsPageEntity->setSections(new CmsSectionCollection([$firstCmsSectionEntity, $secondCmsSectionEntity]));

        return $cmsPageEntity;
    }

    /**
     * @return array<string, array<string, array<string, array<string, array<string, string>>>>>
     */
    private function getCmsSlotConfig(): array
    {
        return [
            'data' => [
                'reviews' => [
                    'elements' => [
                        'myReviewElement' => [
                            'title' => 'myReviewTitle',
                            'content' => 'this blog changed my life',
                        ],
                    ],
                ],
            ],
        ];
    }


    private function getReviewBlock(ChannelBlogEntity $blogEntity): CmsBlockEntity
    {
        $data = new BlogDescriptionReviewsStruct();
        $data->setReviews($this->getBlogReviewResult());

        $reviewSlot = new CmsSlotEntity();
        $reviewSlot->setId(Uuid::randomHex());
        $reviewSlot->setSlot(json_encode($blogEntity->getTranslated(), \JSON_THROW_ON_ERROR));
        $reviewSlot->setData($data);

        $reviewBlock = new CmsBlockEntity();
        $reviewBlock->setId(Uuid::randomHex());
        $reviewBlock->setType(BlogDescriptionReviewsCmsElementResolver::TYPE);
        $reviewBlock->setSlots(new CmsSlotCollection([$reviewSlot]));

        return $reviewBlock;
    }

    private function getCrossSellingBlock(): CmsBlockEntity
    {
        $crossSellingSlot = new CmsSlotEntity();
        $crossSellingSlot->setId(Uuid::randomHex());
        $crossSellingSlot->setSlot('');
        $crossSellingSlot->setData(new CrossSellingStruct());

        $crossSellingBlock = new CmsBlockEntity();
        $crossSellingBlock->setId(Uuid::randomHex());
        $crossSellingBlock->setType(CrossSellingCmsElementResolver::TYPE);
        $crossSellingBlock->setSlots(new CmsSlotCollection([$crossSellingSlot]));

        return $crossSellingBlock;
    }
}
