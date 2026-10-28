<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Search;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class SearchPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected SearchPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): SearchPage
    {
        return $this->page;
    }
}
