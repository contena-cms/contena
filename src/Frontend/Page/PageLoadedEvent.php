<?php declare(strict_types=1);

namespace Contena\Storefront\Page;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\Struct;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
abstract class PageLoadedEvent extends NestedEvent implements ContenaSalesChannelEvent
{
    public function __construct(
        protected SalesChannelContext $salesChannelContext,
        protected Request $request
    ) {
    }

    /**
     * @return Page|Struct
     */
    abstract public function getPage();

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
