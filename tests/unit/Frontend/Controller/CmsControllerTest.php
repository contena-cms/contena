<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Content\Blog\Channel\FindVariant\FindBlogVariantRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRouteResponse;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewLoader;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewResult;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Category\Channel\CategoryRouteResponse;
use Contena\Core\Content\Cms\Channel\CmsRoute;
use Contena\Core\Content\Cms\Channel\CmsRouteResponse;
use Contena\Core\Content\Cms\CmsException;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Contena\Frontend\Controller\CmsController;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CmsController::class)]
class CmsControllerTest extends TestCase
{
    private Stub&CmsRoute $cmsRouteMock;

    private Stub&CategoryRoute $categoryRouteMock;

    private Stub&BlogListingRoute $blogListingRouteMock;

    private CmsControllerStub $controller;

    protected function setUp(): void
    {
        $eventDispatcherMock = static::createStub(EventDispatcher::class);
        $this->cmsRouteMock = static::createStub(CmsRoute::class);
        $this->categoryRouteMock = static::createStub(CategoryRoute::class);
        $this->blogListingRouteMock = static::createStub(BlogListingRoute::class);

        $this->controller = new CmsControllerStub(
            $this->cmsRouteMock,
            $this->categoryRouteMock,
            $this->blogListingRouteMock,
            static::createStub(BlogDetailRoute::class),
            static::createStub(BlogReviewLoader::class),
            static::createStub(FindBlogVariantRoute::class),
            $eventDispatcherMock,
            new StaticSystemConfigService([
                'core.listing.showReview' => true,
            ]),
        );
    }

    public function testPageNoId(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('id'));

        $this->controller->page(null, new Request(), static::createStub(ChannelContext::class));
    }

    public function testPageReturn(): void
    {
        $cmsRouteResponse = new CmsRouteResponse(new CmsPageEntity());
        $this->cmsRouteMock->method('load')->willReturn($cmsRouteResponse);

        $ids = new IdsCollection();

        $this->controller->page($ids->get('page'), new Request(), static::createStub(ChannelContext::class));

        static::assertSame($cmsRouteResponse->getCmsPage(), $this->controller->recorder()->renderFrontendParameters['cmsPage']);
    }

    public function testPageFullReturn(): void
    {
        $cmsRouteResponse = new CmsRouteResponse(new CmsPageEntity());
        $this->cmsRouteMock->method('load')->willReturn($cmsRouteResponse);

        $ids = new IdsCollection();

        $this->controller->pageFull($ids->get('page'), new Request(), static::createStub(ChannelContext::class));

        static::assertSame($cmsRouteResponse->getCmsPage(), $this->controller->recorder()->renderFrontendParameters['page']['cmsPage']);
    }

    public function testCategoryNoId(): void
    {
        $this->expectExceptionObject(RoutingException::missingRequestParameter('navigationId'));

        $this->controller->category(null, new Request(), static::createStub(ChannelContext::class));
    }

    public function testCategoryReturn(): void
    {
        $categoryEntity = new CategoryEntity();
        $categoryEntity->setCmsPage(new CmsPageEntity());
        $categoryRouteResponse = new CategoryRouteResponse($categoryEntity);
        $this->categoryRouteMock->method('load')->willReturn($categoryRouteResponse);

        $ids = new IdsCollection();

        $this->controller->category($ids->get('category'), new Request(), static::createStub(ChannelContext::class));

        static::assertSame($categoryRouteResponse->getCategory()->getCmsPage(), $this->controller->recorder()->renderFrontendParameters['cmsPage']);
    }

    public function testCategoryPageNotFound(): void
    {
        $categoryEntity = new CategoryEntity();
        $categoryRouteResponse = new CategoryRouteResponse($categoryEntity);
        $this->categoryRouteMock->method('load')->willReturn($categoryRouteResponse);

        $navigationId = (new IdsCollection())->get('category');
        $this->expectExceptionObject(CmsException::pageNotFound('navigationId: ' . $navigationId));

        $this->controller->category($navigationId, new Request(), static::createStub(ChannelContext::class));
    }

    public function testFilterReturn(): void
    {
        $ids = new IdsCollection();

        $testAggregations = new \ArrayObject([
            'count' => new CountResult('count', 2),
            'sum' => new SumResult('sum', 2.3),
        ]);
        $blogListingResultMock = static::createStub(BlogListingResult::class);
        $blogListingResultMock->method('getAggregations')->willReturn(
            new AggregationResultCollection(
                $testAggregations
            )
        );

        $request = new Request();

        $blogListingRouteResponse = new BlogListingRouteResponse($blogListingResultMock);
        $this->blogListingRouteMock->method('load')->willReturn($blogListingRouteResponse);

        $response = $this->controller->filter($ids->get('navigation'), $request, static::createStub(ChannelContext::class));

        static::assertSame(
            json_encode($testAggregations, \JSON_THROW_ON_ERROR),
            json_encode(json_decode($response->getContent() ?: '', true, 512, \JSON_THROW_ON_ERROR), \JSON_THROW_ON_ERROR)
        );

        static::assertTrue($request->request->get('only-aggregations'));
        static::assertTrue($request->request->get('reduce-aggregations'));
    }

    public function testSwitchReturn(): void
    {
        $ids = new IdsCollection();

        $request = new Request(
            [
                'elementId' => $ids->get('element'),
                'options' => json_encode([
                    $ids->get('group1') => $ids->get('option1'),
                    $ids->get('group2') => $ids->get('option2'),
                ], \JSON_THROW_ON_ERROR),
            ]
        );

        $this->controller->switchBuyBoxVariant($ids->get('blog'), $request, static::createStub(ChannelContext::class));

        static::assertInstanceOf(ChannelBlogEntity::class, $this->controller->recorder()->renderFrontendParameters['blog']);

        static::assertSame(
            $this->controller->recorder()->renderFrontendParameters,
            [
                'blog' => $this->controller->recorder()->renderFrontendParameters['blog'],
                'configuratorSettings' => null,
                'totalReviews' => 0,
                'elementId' => $ids->get('element'),
            ]
        );
    }

    public function testSwitchReturnWithoutReview(): void
    {
        $ids = new IdsCollection();

        $request = new Request(
            [
                'elementId' => $ids->get('element'),
                'options' => json_encode([
                    $ids->get('group1') => $ids->get('option1'),
                    $ids->get('group2') => $ids->get('option2'),
                ], \JSON_THROW_ON_ERROR),
            ]
        );

        $reviewLoader = $this->createMock(BlogReviewLoader::class);
        $reviewLoader->expects($this->never())->method('load');

        $controller = new CmsControllerStub(
            $this->cmsRouteMock,
            $this->categoryRouteMock,
            $this->blogListingRouteMock,
            static::createStub(BlogDetailRoute::class),
            $reviewLoader,
            static::createStub(FindBlogVariantRoute::class),
            static::createStub(EventDispatcher::class),
            new StaticSystemConfigService([
                'core.listing.showReview' => false,
            ]),
        );

        $context = Generator::generateChannelContext();

        $controller->switchBuyBoxVariant($ids->get('blog'), $request, $context);

        $reviewLoader = $this->createMock(BlogReviewLoader::class);
        $reviewLoader->expects($this->never())->method('load');

        // global config is enabled
        $systemConfig = new StaticSystemConfigService([
            'core.listing.showReview' => true,
        ]);

        // but disabled for current sales channel
        $systemConfig->set('core.listing.showReview', false, $context->getChannelId());

        $controller = new CmsControllerStub(
            $this->cmsRouteMock,
            $this->categoryRouteMock,
            $this->blogListingRouteMock,
            static::createStub(BlogDetailRoute::class),
            $reviewLoader,
            static::createStub(FindBlogVariantRoute::class),
            static::createStub(EventDispatcher::class),
            $systemConfig
        );

        $controller->switchBuyBoxVariant($ids->get('blog'), $request, $context);
    }

    public function testSwitchReturnWithReviews(): void
    {
        $ids = new IdsCollection();

        $request = new Request(
            [
                'elementId' => $ids->get('element'),
                'options' => json_encode([
                    $ids->get('group1') => $ids->get('option1'),
                    $ids->get('group2') => $ids->get('option2'),
                ], \JSON_THROW_ON_ERROR),
            ]
        );

        $result = static::createStub(BlogReviewResult::class);
        $result->method('getTotal')->willReturn(5);

        $reviewLoader = $this->createMock(BlogReviewLoader::class);
        $reviewLoader->expects($this->once())->method('load')->willReturn($result);

        $context = Generator::generateChannelContext();

        // global config is enabled
        $systemConfig = new StaticSystemConfigService([
            'core.listing.showReview' => false,
        ]);

        // but enabled for current sales channel
        $systemConfig->set('core.listing.showReview', true, $context->getChannelId());

        $controller = new CmsControllerStub(
            $this->cmsRouteMock,
            $this->categoryRouteMock,
            $this->blogListingRouteMock,
            static::createStub(BlogDetailRoute::class),
            $reviewLoader,
            static::createStub(FindBlogVariantRoute::class),
            static::createStub(EventDispatcher::class),
            $systemConfig
        );

        $controller->switchBuyBoxVariant($ids->get('blog'), $request, $context);

        static::assertInstanceOf(ChannelBlogEntity::class, $controller->recorder()->renderFrontendParameters['blog']);

        static::assertSame(
            $controller->recorder()->renderFrontendParameters,
            [
                'blog' => $controller->recorder()->renderFrontendParameters['blog'],
                'configuratorSettings' => null,
                'totalReviews' => 5,
                'elementId' => $ids->get('element'),
            ]
        );
    }

    public function testSwitchBuyBoxVariantWithInvalidJsonOptions(): void
    {
        $ids = new IdsCollection();

        $request = new Request(
            [
                'elementId' => $ids->get('element'),
                'options' => 'invalidJsonString',
            ]
        );

        $response = $this->controller->switchBuyBoxVariant(
            $ids->get('blog'),
            $request,
            static::createStub(ChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
