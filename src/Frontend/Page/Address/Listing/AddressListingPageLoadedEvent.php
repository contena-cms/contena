<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Address\Listing;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class AddressListingPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AddressListingPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AddressListingPage
    {
        return $this->page;
    }
}
