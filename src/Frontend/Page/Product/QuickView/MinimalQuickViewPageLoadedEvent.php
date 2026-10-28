<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Product\QuickView;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class MinimalQuickViewPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected MinimalQuickViewPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): MinimalQuickViewPage
    {
        return $this->page;
    }
}
