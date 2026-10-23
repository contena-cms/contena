<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Content\Blog\Channel\Review\BlogReviewsWidgetLoadedHook;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Framework\Routing\RequestTransformer;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Page\Blog\BlogPageLoadedHook;
use Contena\Frontend\Page\Blog\QuickView\BlogQuickViewWidgetLoadedHook;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Content\Blog\Exception\ReviewNotActiveExeption;
use Contena\Core\Content\Blog\Exception\VariantNotFoundException;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class BlogController extends FrontendController
{

    #[Route(
        path: '/detail/{blogId}',
        name: BlogPageSeoUrlRoute::ROUTE_NAME,
        defaults: [PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_GET]
    )]
    public function index(ChannelContext $context, Request $request): Response
    {
        $page = $this->blogPageLoader->load($request, $context);

        $this->hook(new BlogPageLoadedHook($page, $context));

        return $this->renderFrontend(
            '@Frontend/frontend/page/content/blog-detail.html.twig',
            [
                'page' => $page,
                'redirectTo' => BlogPageSeoUrlRoute::ROUTE_NAME,
            ]
        );
    }

    #[Route(
        path: '/detail/{blogId}/switch',
        name: 'frontend.detail.switch',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function switch(string $blogId, Request $request, ChannelContext $channelContext): JsonResponse
    {
        $switchedGroup = $request->query->has('switched') ? (string) $request->query->get('switched') : null;

        try {
            $options = json_decode($request->query->get('options', '[]'), true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $options = [];
        }

        $variantRequestData = [
            'switchedGroup' => $switchedGroup,
            'options' => $options,
        ];

        $variantRequest = $request->duplicate($variantRequestData);

        try {
            $variantResponse = $this->findVariantRoute->load(
                $blogId,
                $variantRequest,
                $channelContext
            );

            $blogId = $variantResponse->getFoundCombination()->getVariantId();
        } catch (VariantNotFoundException|BlogNotFoundException) {
            // nth
        }

        $host = $request->attributes->get(RequestTransformer::CHANNEL_ABSOLUTE_BASE_URL)
            . $request->attributes->get(RequestTransformer::CHANNEL_BASE_URL);

        $url = $this->seoUrlPlaceholderHandler->replace(
            $this->seoUrlPlaceholderHandler->generate(
                BlogPageSeoUrlRoute::ROUTE_NAME,
                ['blogId' => $blogId]
            ),
            $host,
            $channelContext
        );

        return new JsonResponse([
            'url' => $url,
            'blogId' => $blogId,
        ]);
    }

    #[Route(
        path: '/quickview/{blogId}',
        name: 'widgets.quickview.minimal',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_GET]
    )]
    public function quickviewMinimal(Request $request, ChannelContext $context): Response
    {
        $page = $this->minimalQuickViewPageLoader->load($request, $context);

        $this->hook(new BlogQuickViewWidgetLoadedHook($page, $context));

        return $this->renderFrontend('@Frontend/frontend/component/blog/quickview/minimal.html.twig', ['page' => $page]);
    }

    #[Route(
        path: '/blog/{blogId}/rating',
        name: 'frontend.detail.review.save',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
        ],
        methods: [Request::METHOD_POST]
    )]
    public function saveReview(string $blogId, RequestDataBag $data, ChannelContext $context): Response
    {
        if (!Feature::isActive('v6.8.0.0')) {
            try {
                $this->blogReviewSaveRoute->save($blogId, $data, $context);
            } catch (ConstraintViolationException $formViolations) {
                return $this->forwardToRoute('frontend.blog.reviews', [
                    'blogId' => $blogId,
                    'success' => -1,
                    'formViolations' => $formViolations,
                    'data' => $data,
                ], ['blogId' => $blogId]);
            } catch (ReviewNotActiveExeption) {
                throw FrontendException::reviewNotActive();
            }
        } else {
            try {
                $this->blogReviewSaveRoute->save($blogId, $data, $context);
            } catch (ConstraintViolationException $formViolations) {
                return $this->forwardToRoute('frontend.blog.reviews', [
                    'blogId' => $blogId,
                    'success' => -1,
                    'formViolations' => $formViolations,
                    'data' => $data,
                ], ['blogId' => $blogId]);
            }
        }

        $forwardParams = [
            'blogId' => $blogId,
            'success' => 1,
            'data' => $data,
            'parentId' => $data->get('parentId'),
        ];

        if ($data->has('id')) {
            $forwardParams['success'] = 2;
        }

        return $this->forwardToRoute('frontend.blog.reviews', $forwardParams, ['blogId' => $blogId]);
    }

    #[Route(
        path: '/blog/{blogId}/reviews',
        name: 'frontend.blog.reviews',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function loadReviews(string $blogId, Request $request, ChannelContext $context): Response
    {
        $parentId = RequestParamHelper::get($request, 'parentId');
        if (!Feature::isActive('v6.8.0.0')) {
            try {
                $reviews = $this->blogReviewLoader->load($request, $context, $blogId, $parentId);
            } catch (ReviewNotActiveExeption) {
                throw FrontendException::reviewNotActive();
            }
        } else {
            $reviews = $this->blogReviewLoader->load($request, $context, $blogId, $parentId);
        }

        $this->hook(new BlogReviewsWidgetLoadedHook($reviews, $context));

        return $this->renderFrontend(
            'frontend/component/review/review.html.twig',
            [
                'reviews' => $reviews,
                'ratingSuccess' => $request->attributes->get('success'),
                'redirectTo' => RequestParamHelper::get(
                    $request,
                    'redirectTo',
                    $request->attributes->get('_route')
                ),
            ]
        );
    }

    #[Route(
        path: '/blog/{blogId}/purchase-limit',
        name: 'frontend.blog.purchase-limit',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_GET]
    )]
    public function purchaseLimit(string $blogId, Request $request, ChannelContext $context): JsonResponse
    {
        $purchaseLimitRequest = $request->duplicate(['ids' => [$blogId]]);

        $result = $this->blogPurchaseLimitRoute->readBlogsPurchaseLimit($purchaseLimitRequest, $context)->getResult()->first();

        if ($result === null) {
            return new JsonResponse(null, Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'blogId' => $result->getBlogId(),
            'minPurchase' => $result->getMinPurchase(),
            'purchaseSteps' => $result->getPurchaseSteps(),
            'maxPurchase' => $result->getMaxPurchase(),
        ]);
    }

    #[Route(
        path: '/blog/{blogId}/garan-label',
        name: 'frontend.blog.garan-label',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_GET]
    )]
    public function garanLabel(string $blogId, ChannelContext $context): Response
    {
        try {
            $garanLabel = $this->garanLabelRoute->load($blogId, $context)->getObject()->get('svg');
        } catch (BlogNotFoundException) {
            $garanLabel = null;
        }

        return $this->renderFrontend('@Frontend/frontend/component/blog/garan-label-modal.html.twig', ['garanLabel' => $garanLabel]);
    }
}
