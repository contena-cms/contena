<?php declare(strict_types=1);

namespace Contena\Storefront\Pagelet\Country;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Country\SalesChannel\AbstractCountryStateRoute;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
#[Package('discovery')]
class CountryStateDataPageletLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractCountryStateRoute $countryStateRoute,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

}
