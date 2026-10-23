<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;

/**
 * @extends ChannelApiResponse<UserAddressEntity>
 */
class UpsertAddressRouteResponse extends ChannelApiResponse
{
    public function getAddress(): UserAddressEntity
    {
        return $this->object;
    }
}
