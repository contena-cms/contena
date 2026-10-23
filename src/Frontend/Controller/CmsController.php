<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Content\Blog\Channel\Detail\AbstractBlogDetailRoute;
use Contena\Core\Content\Blog\Channel\FindVariant\AbstractFindBlogVariantRoute;
use Contena\Core\Content\Blog\Channel\Listing\AbstractBlogListingRoute;
use Contena\Core\Content\Blog\Channel\Review\AbstractBlogReviewLoader;
use Contena\Core\Content\Category\Channel\AbstractCategoryRoute;
use Contena\Core\Content\Cms\Channel\AbstractCmsRoute;
use Contena\Core\Content\Cms\CmsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Frontend\Event\SwitchBuyBoxVariantEvent;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Page\Cms\CmsPageLoadedHook;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class CmsController extends FrontendController
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCmsRoute $cmsRoute,
        private readonly AbstractCategoryRoute $categoryRoute,
        private readonly AbstractBlogListingRoute $listingRoute,
        private readonly AbstractBlogDetailRoute $blogRoute,
        private readonly AbstractBlogReviewLoader $blogReviewLoader,
        private readonly AbstractFindBlogVariantRoute $findVariantRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService
    ) {
    }

    /**
     * Rendering a CMS layout as a widget, meaning that the layout is rendered standalone without the surrounding page template.
     * Use this if you want to load content via JS and embed into an existing page or modal.
     */
    #[Route(
        path: '/widgets/cms/{id}',
        name: 'frontend.cms.page',
        defaults: [
            'id' => null,
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function page(?string $id, Request $request, ChannelContext $channelContext): Response
    {
        if (!$id) {
            throw RoutingException::missingRequestParameter('id');
        }

        $page = $this->cmsRoute->load($id, $request, $channelContext)->getCmsPage();

        $this->hook(new CmsPageLoadedHook($page, $channelContext));

        $response = $this->renderFrontend('@Frontend/frontend/page/content/detail.html.twig', ['cmsPage' => $page]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * Rendering a CMS layout as a full page, example including stylesheets, scripts, header, footer, etc.
     * Use this for internal page links pointing to a layout.
     */
    #[Route(
        path: '/page/cms/{id}',
        name: 'frontend.cms.page.full',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function pageFull(string $id, Request $request, ChannelContext $channelContext): Response
    {
        $page = $this->cmsRoute->load($id, $request, $channelContext)->getCmsPage();
        $this->hook(new CmsPageLoadedHook($page, $channelContext));

        $response = $this->renderFrontend('@Frontend/frontend/page/content/index.html.twig', ['page' => ['cmsPage' => $page]]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * Navigation id is required to load the slot config for the navigation
     */
    #[Route(
        path: '/widgets/cms/navigation/{navigationId}',
        name: 'frontend.cms.navigation.page',
        defaults: [
            'navigationId' => null,
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function category(?string $navigationId, Request $request, ChannelContext $channelContext): Response
    {
        if (!$navigationId) {
            throw RoutingException::missingRequestParameter('navigationId');
        }

        $category = $this->categoryRoute->load($navigationId, $request, $channelContext)->getCategory();

        $page = $category->getCmsPage();
        if (!$page) {
            throw CmsException::pageNotFound('navigationId: ' . $navigationId);
        }

        $this->hook(new CmsPageLoadedHook($page, $channelContext));

        $response = $this->renderFrontend('@Frontend/frontend/page/content/detail.html.twig', ['cmsPage' => $page]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * Route to load the listing filters
     */
    #[Route(
        path: '/widgets/cms/navigation/{navigationId}/filter',
        name: 'frontend.cms.navigation.filter',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function filter(string $navigationId, Request $request, ChannelContext $context): Response
    {
        // Allows to fetch only aggregations over the gateway.
        $request->request->set('only-aggregations', true);

        // Allows to convert all post-filters to filters. This leads to the fact that only aggregation values are returned, which are combinable with the previous applied filters.
        $request->request->set('reduce-aggregations', true);

        $listing = $this->listingRoute
            ->load($navigationId, $request, $context, new Criteria())
            ->getResult();

        $mapped = [];
        foreach ($listing->getAggregations() as $aggregation) {
            $mapped[$aggregation->getName()] = $aggregation;
        }

        $response = new JsonResponse($mapped);

        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * Route to load the cms element buy box blog config which assigned to the provided blog id.
     * Blog id is required to load the slot config for the buy box
     */
    #[Route(
        path: '/widgets/cms/buybox/{blogId}/switch',
        name: 'frontend.cms.buybox.switch',
        defaults: [
            'blogId' => null,
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET]
    )]
    public function switchBuyBoxVariant(string $blogId, Request $request, ChannelContext $context): Response
    {
        /** @var string $elementId */
        $elementId = $request->query->get('elementId');

        /** @var string[]|null $options */
        $options = json_decode($request->query->get('options', ''), true);

        $variantRequestData = [
            'switchedGroup' => $request->query->get('switched'),
            'options' => $options ?? [],
        ];
        $variantRequest = $request->duplicate($variantRequestData);

        $variantResponse = $this->findVariantRoute->load(
            $blogId,
            $variantRequest,
            $context
        );

        $newBlogId = $variantResponse->getFoundCombination()->getVariantId();

        $result = $this->blogRoute->load($newBlogId, $request, $context, new Criteria());
        $blog = $result->getBlog();
        $configurator = $result->getConfigurator();

        $reviewTotal = 0;
        if ($this->systemConfigService->getBool('core.listing.showReview', $context->getChannelId())) {
            $reviews = $this->blogReviewLoader->load($request, $context, $blog->getId(), $blog->getParentId());
            $reviewTotal = $reviews->getTotal();
        }

        $event = new SwitchBuyBoxVariantEvent($elementId, $blog, $configurator, $request, $context);
        $this->eventDispatcher->dispatch($event);

        $response = $this->renderFrontend('@Frontend/frontend/component/buy-widget/buy-widget.html.twig', [
            'blog' => $blog,
            'configuratorSettings' => $configurator,
            'totalReviews' => $reviewTotal,
            'elementId' => $elementId,
        ]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }
}
