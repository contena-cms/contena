<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\Channel\StoreApiResponse;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;

/**
 * @extends StoreApiResponse<UserAddressEntity>
 */
class UpsertAddressRouteResponse extends StoreApiResponse
{
    public function getAddress(): UserAddressEntity
    {
        return $this->object;
    }
}
