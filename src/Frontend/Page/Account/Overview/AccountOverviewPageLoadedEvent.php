<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Account\Overview;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class AccountOverviewPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountOverviewPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountOverviewPage
    {
        return $this->page;
    }
}
