<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Suggest;

use Contena\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ProductListingResult>
 */
#[Package('discovery')]
class ProductSuggestRouteResponse extends StoreApiResponse
{
    public function getListingResult(): ProductListingResult
    {
        return $this->object;
    }
}
