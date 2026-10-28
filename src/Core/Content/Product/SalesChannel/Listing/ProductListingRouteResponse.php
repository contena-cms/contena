<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Listing;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ProductListingResult>
 */
#[Package('inventory')]
class ProductListingRouteResponse extends StoreApiResponse
{
    public function getResult(): ProductListingResult
    {
        return $this->object;
    }
}
