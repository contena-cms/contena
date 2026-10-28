<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CustomerAddressEntity>
 */
#[Package('checkout')]
class UpsertAddressRouteResponse extends StoreApiResponse
{
    public function getAddress(): CustomerAddressEntity
    {
        return $this->object;
    }
}
