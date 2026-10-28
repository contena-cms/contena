<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet\Menu\Offcanvas;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MenuOffcanvasPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected MenuOffcanvasPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): MenuOffcanvasPagelet
    {
        return $this->pagelet;
    }
}
