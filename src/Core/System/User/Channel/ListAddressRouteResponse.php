<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\Aggregate\UserAddress\UserAddressCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\StoreApiResponse;

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
