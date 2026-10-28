<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Account\Login;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('checkout')]
class AccountLoginPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountLoginPage $page,
        SalesChannelContext $salesChannelContext,
        Request $request,
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPage(): AccountLoginPage
    {
        return $this->page;
    }
}
