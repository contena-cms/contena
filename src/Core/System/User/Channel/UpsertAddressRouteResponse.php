<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\StoreApiResponse;

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
