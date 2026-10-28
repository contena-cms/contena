<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Listing;

use Contena\Core\Content\Product\Events\ProductListingCriteriaEvent;
use Contena\Core\Content\Product\Events\ProductListingResultEvent;
use Contena\Core\Content\Product\SalesChannel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('inventory')]
class ResolveCriteriaProductListingRoute extends AbstractProductListingRoute
{
    public const STATE = 'listing-route-context';

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductListingRoute $decorated,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CompositeListingProcessor $processor
    ) {
    }

    public function getDecorated(): AbstractProductListingRoute
    {
        return $this->decorated;
    }

    public function load(string $categoryId, Request $request, SalesChannelContext $context, Criteria $criteria): ProductListingRouteResponse
    {
        $criteria->addState(self::STATE);

        $this->processor->prepare($request, $criteria, $context);

        $this->eventDispatcher->dispatch(
            new ProductListingCriteriaEvent($request, $criteria, $context)
        );

        $response = $this->getDecorated()->load($categoryId, $request, $context, $criteria);

        $response->getResult()->addCurrentFilter('navigationId', $categoryId);

        $this->processor->process($request, $response->getResult(), $context);

        $this->eventDispatcher->dispatch(
            new ProductListingResultEvent($request, $response->getResult(), $context)
        );

        $response->getResult()->getAvailableSortings()->removeByKey(
            ResolvedCriteriaProductSearchRoute::DEFAULT_SEARCH_SORT
        );

        return $response;
    }
}
