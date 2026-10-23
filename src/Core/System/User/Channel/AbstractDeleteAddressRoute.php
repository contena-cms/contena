<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\User\UserEntity;

/**
 * This route can be used to delete addresses
 */
abstract class AbstractDeleteAddressRoute
{
    abstract public function getDecorated(): AbstractDeleteAddressRoute;

    abstract public function delete(string $addressId, ChannelContext $context, UserEntity $user): NoContentResponse;
}
