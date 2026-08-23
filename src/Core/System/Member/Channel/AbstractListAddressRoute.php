<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Member\MemberEntity;

/**
 * This route can be used to list all addresses of an member
 */
abstract class AbstractListAddressRoute
{
    abstract public function load(Criteria $criteria, ChannelContext $context, MemberEntity $member): ListAddressRouteResponse;

    abstract public function getDecorated(): AbstractListAddressRoute;
}
