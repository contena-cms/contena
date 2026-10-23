<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Frontend\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewEntity;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\FindVariant\FindBlogVariantRoute;
use Contena\Core\Content\Blog\Channel\FindVariant\FindBlogVariantRouteResponse;
use Contena\Core\Content\Blog\Channel\FindVariant\FoundCombination;
use Contena\Core\Content\Blog\Channel\Garan\AbstractGaranLabelRoute;
use Contena\Core\Content\Blog\Channel\Garan\GaranLabelRouteResponse;
use Contena\Core\Content\Blog\Channel\PurchaseLimit\AbstractBlogPurchaseLimitRoute;
use Contena\Core\Content\Blog\Channel\PurchaseLimit\BlogPurchaseLimit;
use Contena\Core\Content\Blog\Channel\PurchaseLimit\BlogPurchaseLimitCollection;
use Contena\Core\Content\Blog\Channel\PurchaseLimit\BlogPurchaseLimitRouteResponse;
use Contena\Core\Content\Blog\Channel\Review\AbstractBlogReviewSaveRoute;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewLoader;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewResult;
use Contena\Core\Content\Blog\Channel\Review\BlogReviewsWidgetLoadedHook;
use Contena\Core\Content\Blog\Channel\Review\RatingMatrix;
use Contena\Core\Content\Blog\Exception\VariantNotFoundException;
use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\Exception\ConstraintViolationException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Frontend\Controller\BlogController;
use Contena\Frontend\Page\Blog\BlogPage;
use Contena\Frontend\Page\Blog\BlogPageLoader;
use Contena\Frontend\Page\Blog\QuickView\BlogQuickViewWidgetLoadedHook;
use Contena\Frontend\Page\Blog\QuickView\MinimalQuickViewPage;
use Contena\Frontend\Page\Blog\QuickView\MinimalQuickViewPageLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\ConstraintViolationList;

/**
 * @internal
 */
#[CoversClass(BlogController::class)]
class BlogControllerTest extends TestCase
{
    private Stub&BlogPageLoader $blogPageLoaderMock;

    private Stub&FindBlogVariantRoute $findVariantRouteMock;

    private Stub&SeoUrlPlaceholderHandlerInterface $seoUrlPlaceholderHandlerMock;

    private Stub&MinimalQuickViewPageLoader $minimalQuickViewPageLoaderMock;

    private Stub&AbstractBlogReviewSaveRoute $blogReviewSaveRouteMock;

    private Stub&BlogReviewLoader $blogReviewLoaderMock;

    private Stub&AbstractBlogPurchaseLimitRoute $blogPurchaseLimitRouteMock;

    private Stub&AbstractGaranLabelRoute $garanLabelRouteMock;


    protected function setUp(): void
    {
        $this->blogPageLoaderMock = static::createStub(BlogPageLoader::class);
        $this->findVariantRouteMock = static::createStub(FindBlogVariantRoute::class);
        $this->seoUrlPlaceholderHandlerMock = static::createStub(SeoUrlPlaceholderHandlerInterface::class);
        $this->minimalQuickViewPageLoaderMock = static::createStub(MinimalQuickViewPageLoader::class);
        $this->blogReviewSaveRouteMock = static::createStub(AbstractBlogReviewSaveRoute::class);
        $this->blogReviewLoaderMock = static::createStub(BlogReviewLoader::class);
        $this->blogPurchaseLimitRouteMock = static::createStub(AbstractBlogPurchaseLimitRoute::class);
        $this->garanLabelRouteMock = static::createStub(AbstractGaranLabelRoute::class);

        $this->controller = new BlogControllerStub(
            $this->blogPageLoaderMock,
            $this->findVariantRouteMock,
            $this->minimalQuickViewPageLoaderMock,
            $this->blogReviewSaveRouteMock,
            $this->seoUrlPlaceholderHandlerMock,
            $this->blogReviewLoaderMock,
            $this->blogPurchaseLimitRouteMock,
            $this->garanLabelRouteMock,
        );
    }

    public function testIndexCmsPage(): void
    {
        $blogEntity = new ChannelBlogEntity();
        $blogEntity->setId('test');
        $blogPage = new BlogPage();
        $blogPage->setBlog($blogEntity);
        $blogPage->setCmsPage(new CmsPageEntity());

        $this->blogPageLoaderMock->method('load')->willReturn($blogPage);

        $response = $this->controller->index(static::createStub(ChannelContext::class), new Request());

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertInstanceOf(BlogPage::class, $this->controller->recorder()->renderFrontendParameters['page']);
        static::assertSame('test', $this->controller->recorder()->renderFrontendParameters['page']->getBlog()->getId());
        static::assertSame('@Frontend/frontend/page/content/blog-detail.html.twig', $this->controller->recorder()->renderFrontendView);
    }

    public function testSwitchNoVariantReturn(): void
    {
        $response = $this->controller->switch(Uuid::randomHex(), new Request(), static::createStub(ChannelContext::class));

        static::assertSame('{"url":"","blogId":""}', $response->getContent());
    }

    public function testSwitchVariantReturn(): void
    {
        $ids = new IdsCollection();

        $options = [
            $ids->get('group1') => $ids->get('option1'),
            $ids->get('group2') => $ids->get('option2'),
        ];

        $request = new Request(
            [
                'switched' => $ids->get('element'),
                'options' => json_encode($options, \JSON_THROW_ON_ERROR),
            ]
        );

        $this->findVariantRouteMock->method('load')
            ->willReturn(
                new FindBlogVariantRouteResponse(new FoundCombination($ids->get('variantId'), $options))
            );

        $this->seoUrlPlaceholderHandlerMock->method('generate')->willReturn('https://test.com/test');

        $this->seoUrlPlaceholderHandlerMock->method('replace')->willReturnArgument(0);

        $response = $this->controller->switch($ids->get('blog'), $request, static::createStub(ChannelContext::class));

        static::assertSame('{"url":"https:\/\/test.com\/test","blogId":"' . $ids->get('variantId') . '"}', $response->getContent());
    }

    public function testSwitchVariantException(): void
    {
        $ids = new IdsCollection();

        $options = [
            $ids->get('group1') => $ids->get('option1'),
            $ids->get('group2') => $ids->get('option2'),
        ];

        $this->findVariantRouteMock->method('load')->willThrowException(new VariantNotFoundException($ids->get('blog'), $options));

        $response = $this->controller->switch($ids->get('blog'), new Request(), static::createStub(ChannelContext::class));

        static::assertSame('{"url":"","blogId":"' . $ids->get('blog') . '"}', $response->getContent());
    }

    public function testQuickViewMinimal(): void
    {
        $ids = new IdsCollection();

        $request = new Request(['blogId' => $ids->get('blogId')]);
        $this->minimalQuickViewPageLoaderMock->method('load')->willReturn(new MinimalQuickViewPage(new BlogEntity()));

        $response = $this->controller->quickviewMinimal(
            $request,
            static::createStub(ChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertInstanceOf(MinimalQuickViewPage::class, $this->controller->recorder()->renderFrontendParameters['page']);
        static::assertInstanceOf(BlogQuickViewWidgetLoadedHook::class, $this->controller->recorder()->calledHook);
    }

    public function testSaveReview(): void
    {
        $ids = new IdsCollection();

        $requestBag = new RequestDataBag(['test' => 'test']);

        $this->blogReviewSaveRouteMock->method('save')->willReturn(new NoContentResponse());

        $response = $this->controller->saveReview(
            $ids->get('blogId'),
            $requestBag,
            static::createStub(ChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('frontend.blog.reviews', $this->controller->recorder()->forwardToRoute);
        static::assertSame(
            [
                'blogId' => $ids->get('blogId'),
                'success' => 1,
                'data' => $requestBag,
                'parentId' => null,
            ],
            $this->controller->recorder()->forwardToRouteAttributes
        );
        static::assertSame(['blogId' => $ids->get('blogId')], $this->controller->recorder()->forwardToRouteParameters);

        $requestBag->set('id', 'any');

        $response = $this->controller->saveReview(
            $ids->get('blogId'),
            $requestBag,
            static::createStub(ChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('frontend.blog.reviews', $this->controller->recorder()->forwardToRoute);
        static::assertSame(
            [
                'blogId' => $ids->get('blogId'),
                'success' => 2,
                'data' => $requestBag,
                'parentId' => null,
            ],
            $this->controller->recorder()->forwardToRouteAttributes
        );
    }

    public function testSaveReviewViolation(): void
    {
        $ids = new IdsCollection();

        $requestBag = new RequestDataBag(['test' => 'test']);

        $violations = new ConstraintViolationException(new ConstraintViolationList(), []);

        $this->blogReviewSaveRouteMock->method('save')->willThrowException($violations);

        $response = $this->controller->saveReview(
            $ids->get('blogId'),
            new RequestDataBag(['test' => 'test']),
            static::createStub(ChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('frontend.blog.reviews', $this->controller->recorder()->forwardToRoute);
        static::assertEquals(
            [
                'blogId' => $ids->get('blogId'),
                'success' => -1,
                'data' => $requestBag,
                'formViolations' => $violations,
            ],
            $this->controller->recorder()->forwardToRouteAttributes
        );
        static::assertSame(['blogId' => $ids->get('blogId')], $this->controller->recorder()->forwardToRouteParameters);
    }

    public function testLoadReviewResults(): void
    {
        $ids = new IdsCollection();

        $blogId = Uuid::randomHex();
        $parentId = Uuid::randomHex();

        $request = new Request([
            'test' => 'test',
            'blogId' => $blogId,
            'parentId' => $parentId,
            'redirectTo' => 'frontend.blog.reviews',
        ]);

        $blogReview = new BlogReviewEntity();
        $blogReview->setUniqueIdentifier($ids->get('blogReview'));
        $reviewResult = BlogReviewResult::fromSearchResult(
            new EntitySearchResult(
                'blog_review',
                1,
                new BlogReviewCollection([$blogReview]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            ),
            new RatingMatrix([]),
            $blogId,
            1,
            null,
            $parentId,
        );

        $this->blogReviewLoaderMock->method('load')->willReturn($reviewResult);

        $response = $this->controller->loadReviews(
            $blogId,
            $request,
            static::createStub(ChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('frontend/component/review/review.html.twig', $this->controller->recorder()->renderFrontendView);
        static::assertSame(
            [
                'reviews' => $reviewResult,
                'ratingSuccess' => null,
                'redirectTo' => 'frontend.blog.reviews',
            ],
            $this->controller->recorder()->renderFrontendParameters
        );

        static::assertInstanceOf(BlogReviewsWidgetLoadedHook::class, $this->controller->recorder()->calledHook);
    }

    public function testPurchaseLimit(): void
    {
        $blogId = Uuid::randomHex();
        $collection = new BlogPurchaseLimitCollection([
            new BlogPurchaseLimit(
                blogId: $blogId,
                minPurchase: 1,
                purchaseSteps: 1,
                maxPurchase: 10,
                stock: 100
            ),
        ]);

        $this->blogPurchaseLimitRouteMock
            ->method('readBlogsPurchaseLimit')
            ->willReturn(new BlogPurchaseLimitRouteResponse($collection));

        $response = $this->controller->purchaseLimit(
            $blogId,
            new Request(),
            static::createStub(ChannelContext::class)
        );

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(
            json_encode([
                'blogId' => $blogId,
                'minPurchase' => 1,
                'purchaseSteps' => 1,
                'maxPurchase' => 10,
            ]),
            $response->getContent()
        );
    }

    public function testPurchaseLimit404WhenBlogNotFound(): void
    {
        $this->blogPurchaseLimitRouteMock
            ->method('readBlogsPurchaseLimit')
            ->willReturn(new BlogPurchaseLimitRouteResponse(new BlogPurchaseLimitCollection()));

        $response = $this->controller->purchaseLimit(
            Uuid::randomHex(),
            new Request(),
            static::createStub(ChannelContext::class)
        );

        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testGaranLabelRendersModalWithFullLabel(): void
    {
        $this->garanLabelRouteMock->method('load')->willReturn(new GaranLabelRouteResponse('<svg>full</svg>', '<svg>nested</svg>'));

        $response = $this->controller->garanLabel(Uuid::randomHex(), static::createStub(ChannelContext::class));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame('@Frontend/frontend/component/blog/garan-label-modal.html.twig', $this->controller->recorder()->renderFrontendView);
        static::assertSame(['garanLabel' => '<svg>full</svg>'], $this->controller->recorder()->renderFrontendParameters);
    }

    public function testGaranLabelRendersModalWithoutLabelForUnavailableBlog(): void
    {
        $blogId = Uuid::randomHex();
        $this->garanLabelRouteMock->method('load')->willThrowException(BlogException::blogNotFound($blogId));

        $response = $this->controller->garanLabel($blogId, static::createStub(ChannelContext::class));

        static::assertSame(Response::HTTP_OK, $response->getStatusCode());
        static::assertSame(['garanLabel' => null], $this->controller->recorder()->renderFrontendParameters);
    }
}
