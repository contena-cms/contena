<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Address\Detail;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class AddressDetailPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AddressDetailPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AddressDetailPage
    {
        return $this->page;
    }
}
