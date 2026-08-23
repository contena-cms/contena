<?php declare(strict_types=1);

namespace Contena\Frontend\Controller;

use Contena\Core\Content\Blog\Channel\Search\AbstractBlogSearchRoute;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Framework\Routing\FrontendRouteScope;
use Contena\Frontend\Page\Search\SearchPageLoader;
use Contena\Frontend\Page\Suggest\SuggestPageLoader;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a channel-api route to get or put data
 */
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [FrontendRouteScope::ID]])]
class SearchController extends FrontendController
{
    public function __construct(
        private readonly SearchPageLoader $searchPageLoader,
        private readonly SuggestPageLoader $suggestPageLoader,
        private readonly AbstractBlogSearchRoute $blogSearchRoute
    ) {
    }

    #[Route(
        path: '/search',
        name: 'frontend.search.page',
        methods: [Request::METHOD_GET]
    )]
    public function search(ChannelContext $context, Request $request): Response
    {
        try {
            $page = $this->searchPageLoader->load($request, $context);
        } catch (RoutingException $e) {
            if ($e->getErrorCode() !== RoutingException::MISSING_REQUEST_PARAMETER_CODE) {
                throw $e;
            }

            return $this->forwardToRoute('frontend.home.page');
        }

        return $this->renderFrontend('@Frontend/frontend/page/search/index.html.twig', ['page' => $page]);
    }

    #[Route(
        path: '/suggest',
        name: 'frontend.search.suggest',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_GET]
    )]
    public function suggest(ChannelContext $context, Request $request): Response
    {
        if (!$request->request->has('no-aggregations')) {
            $request->request->set('no-aggregations', true);
        }

        $page = $this->suggestPageLoader->load($request, $context);

        return $this->renderFrontend('@Frontend/frontend/layout/header/search-suggest.html.twig', ['page' => $page]);
    }

    /**
     * Route to load the listing filters
     */
    #[Route(
        path: '/widgets/search',
        name: 'widgets.search.pagelet.v2',
        defaults: ['XmlHttpRequest' => true],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function ajax(Request $request, ChannelContext $context): Response
    {
        $request->request->set('no-aggregations', true);

        $page = $this->searchPageLoader->load($request, $context);
        $response = $this->renderFrontend('@Frontend/frontend/page/search/search-pagelet.html.twig', ['page' => $page]);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }

    /**
     * Route to load the available listing filters
     */
    #[Route(
        path: '/widgets/search/filter',
        name: 'widgets.search.filter',
        defaults: [
            'XmlHttpRequest' => true,
            PlatformRequest::ATTRIBUTE_HTTP_CACHE => true,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST]
    )]
    public function filter(Request $request, ChannelContext $context): Response
    {
        $term = RequestParamHelper::get($request, 'search');
        if (!$term) {
            throw RoutingException::missingRequestParameter('search');
        }

        // Allows to fetch only aggregations over the gateway.
        $request->request->set('only-aggregations', true);
        // Allows to convert all post-filters to filters. This leads to the fact that only aggregation values are returned, which are combinable with the previous applied filters.
        $request->request->set('reduce-aggregations', true);
        $criteria = new Criteria();
        $criteria->setTitle('search-page');

        $result = $this->blogSearchRoute
            ->load($request, $context, $criteria)
            ->getListingResult();
        $mapped = [];

        foreach ($result->getAggregations() as $aggregation) {
            $mapped[$aggregation->getName()] = $aggregation;
        }

        $response = new JsonResponse($mapped);
        $response->headers->set('x-robots-tag', 'noindex');

        return $response;
    }
}
