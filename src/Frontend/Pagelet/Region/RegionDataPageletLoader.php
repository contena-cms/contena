<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Region;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Region\Channel\AbstractRegionRoute;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a channel-api route to get or put data.
 */
class RegionDataPageletLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractRegionRoute $regionRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function load(string $countryId, ?string $parentId, Request $request, ChannelContext $context): RegionDataPagelet
    {
        $page = new RegionDataPagelet();
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));

        $this->eventDispatcher->dispatch(new RegionDataPageletCriteriaEvent($criteria, $context, $request));

        $regionRouteResponse = $this->regionRoute->load($countryId, $request, $criteria, $context);
        $page->setRegions($regionRouteResponse->getRegions());

        $this->eventDispatcher->dispatch(new RegionDataPageletLoadedEvent($page, $context, $request));

        return $page;
    }
}
