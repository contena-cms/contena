<?php declare(strict_types=1);

namespace Contena\Core\System\Currency\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route can be used to load all currencies of the authenticated sales channel.
 * With this route it is also possible to send the standard API parameters such as: 'page', 'limit', 'filter', etc.
 */
abstract class AbstractCurrencyRoute
{
    abstract public function getDecorated(): AbstractCurrencyRoute;

    abstract public function load(Request $request, ChannelContext $context, Criteria $criteria): CurrencyRouteResponse;
}
