<?php declare(strict_types=1);

namespace Contena\Core\System\Region\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load all regions of a given country.
 * With this route it is also possible to send the standard API parameters such as: 'page', 'limit', 'filter', etc.
 */
abstract class AbstractRegionRoute
{
    abstract public function load(string $countryId, Request $request, Criteria $criteria, ChannelContext $context): RegionRouteResponse;

    abstract protected function getDecorated(): AbstractRegionRoute;
}
