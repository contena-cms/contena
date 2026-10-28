<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Navigation;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class NavigationPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected NavigationPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): NavigationPage
    {
        return $this->page;
    }
}
