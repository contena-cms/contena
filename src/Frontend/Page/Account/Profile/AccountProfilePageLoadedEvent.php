<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Account\Profile;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class AccountProfilePageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountProfilePage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountProfilePage
    {
        return $this->page;
    }
}
