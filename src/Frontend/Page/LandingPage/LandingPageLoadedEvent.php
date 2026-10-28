<?php declare(strict_types=1);

namespace Contena\Storefront\Page\LandingPage;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class LandingPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected LandingPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): LandingPage
    {
        return $this->page;
    }
}
