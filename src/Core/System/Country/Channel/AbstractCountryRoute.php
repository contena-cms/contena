<?php declare(strict_types=1);

namespace Contena\Core\System\Country\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load all countries of the authenticated channel.
 * With this route it is also possible to send the standard API parameters such as: 'page', 'limit', 'filter', etc.
 */
abstract class AbstractCountryRoute
{
    abstract public function load(Request $request, Criteria $criteria, ChannelContext $context): CountryRouteResponse;

    abstract protected function getDecorated(): AbstractCountryRoute;
}
