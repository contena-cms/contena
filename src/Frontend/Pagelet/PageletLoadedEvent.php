<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
abstract class PageletLoadedEvent extends NestedEvent implements ContenaSalesChannelEvent
{
    public function __construct(
        protected SalesChannelContext $salesChannelContext,
        protected Request $request,
    ) {
    }

    /**
     * @return Pagelet
     */
    abstract public function getPagelet();

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
