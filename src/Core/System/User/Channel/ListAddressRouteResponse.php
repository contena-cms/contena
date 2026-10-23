<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\StoreApiResponse;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;

/**
 * @extends StoreApiResponse<EntitySearchResult<UserAddressCollection>>
 */
class ListAddressRouteResponse extends StoreApiResponse
{
    public function getAddressCollection(): UserAddressCollection
    {
        return $this->object->getEntities();
    }
}
