<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet\Footer;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class FooterPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected FooterPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): FooterPagelet
    {
        return $this->pagelet;
    }
}
