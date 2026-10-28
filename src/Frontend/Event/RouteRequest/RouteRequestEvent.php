<?php declare(strict_types=1);

namespace Contena\Storefront\Event\RouteRequest;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

#[Package('discovery')]
abstract class RouteRequestEvent extends NestedEvent implements ContenaSalesChannelEvent
{
    private readonly Criteria $criteria;

    public function __construct(
        private readonly Request $storefrontRequest,
        private readonly Request $storeApiRequest,
        private readonly SalesChannelContext $salesChannelContext,
        ?Criteria $criteria = null
    ) {
        $this->criteria = $criteria ?? new Criteria();
    }

    public function getStorefrontRequest(): Request
    {
        return $this->storefrontRequest;
    }

    public function getStoreApiRequest(): Request
    {
        return $this->storeApiRequest;
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
