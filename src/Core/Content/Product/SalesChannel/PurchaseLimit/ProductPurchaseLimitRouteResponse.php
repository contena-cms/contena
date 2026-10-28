<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\PurchaseLimit;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @codeCoverageIgnore
 *
 * @extends StoreApiResponse<ProductPurchaseLimitCollection>
 */
#[Package('inventory')]
class ProductPurchaseLimitRouteResponse extends StoreApiResponse
{
    public function getResult(): ProductPurchaseLimitCollection
    {
        return $this->object;
    }
}
