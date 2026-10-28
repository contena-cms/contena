<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel;

use Contena\Core\Content\Product\ProductCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<ProductCollection>>
 */
#[Package('inventory')]
class ProductListResponse extends StoreApiResponse
{
    public function getProducts(): ProductCollection
    {
        return $this->object->getEntities();
    }
}
