<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Search;

use Contena\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Contena\Core\Content\Product\Extension\ProductSearchRouteExtension;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Contena\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Contena\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Package('inventory')]
#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class ProductSearchRoute extends AbstractProductSearchRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ProductSearchBuilderInterface $searchBuilder,
        private readonly ProductListingLoader $productListingLoader,
        private readonly ExtensionDispatcher $extensions,
    ) {
    }

    public function getDecorated(): AbstractProductSearchRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/search',
        name: 'store-api.search',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => ProductDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSearchRouteResponse
    {
        return $this->extensions->publish(
            name: ProductSearchRouteExtension::NAME,
            extension: new ProductSearchRouteExtension($request, $context, $criteria),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, SalesChannelContext $context, Criteria $criteria): ProductSearchRouteResponse
    {
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $criteria->addFilter(
            new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_SEARCH)
        );

        if (RequestParamHelper::get($request, 'search')) {
            $this->searchBuilder->build($request, $criteria, $context);
        }

        $result = $this->productListingLoader->load($criteria, $context);

        $result = ProductListingResult::createFrom($result);

        return new ProductSearchRouteResponse($result);
    }
}
