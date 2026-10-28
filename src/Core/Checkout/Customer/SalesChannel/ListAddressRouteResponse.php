<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<CustomerAddressCollection>>
 */
#[Package('checkout')]
class ListAddressRouteResponse extends StoreApiResponse
{
    public function getAddressCollection(): CustomerAddressCollection
    {
        return $this->object->getEntities();
    }
}
