<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * This route can be used to list all addresses of an user
 */
abstract class AbstractListAddressRoute
{
    abstract public function load(Criteria $criteria, ChannelContext $context, UserEntity $user): ListAddressRouteResponse;

    abstract public function getDecorated(): AbstractListAddressRoute;
}
