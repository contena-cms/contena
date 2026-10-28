<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Events;

use Contena\Core\Content\Product\SalesChannel\Listing\FilterCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductListingCollectFilterEvent extends NestedEvent implements ContenaSalesChannelEvent
{
    public function __construct(
        protected Request $request,
        protected FilterCollection $filters,
        protected SalesChannelContext $context,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getFilters(): FilterCollection
    {
        return $this->filters;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->context;
    }
}
