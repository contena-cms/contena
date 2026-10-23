<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Country;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Country\Channel\AbstractCountryStateRoute;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a store-api route to get or put data.
 */
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

    public function load(string $countryId, Request $request, ChannelContext $context): CountryStateDataPagelet
    {
        $page = new CountryStateDataPagelet();

        $criteria = new Criteria();

        $this->eventDispatcher->dispatch(new CountryStateDataPageletCriteriaEvent($criteria, $context, $request));

        $countryRouteResponse = $this->countryStateRoute->load($countryId, $request, $criteria, $context);

        $page->setStates($countryRouteResponse->getStates());

        $this->eventDispatcher->dispatch(new CountryStateDataPageletLoadedEvent($page, $context, $request));

        return $page;
    }
}
