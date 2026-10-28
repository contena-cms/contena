<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet\Country;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Storefront\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class CountryStateDataPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected CountryStateDataPagelet $pagelet,
        SalesChannelContext $salesChannelContext,
        Request $request
    ) {
        parent::__construct($salesChannelContext, $request);
    }

    public function getPagelet(): CountryStateDataPagelet
    {
        return $this->pagelet;
    }
}
