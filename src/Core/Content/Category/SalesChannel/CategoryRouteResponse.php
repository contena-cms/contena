<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\SalesChannel;

use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CategoryEntity>
 */
#[Package('discovery')]
class CategoryRouteResponse extends StoreApiResponse
{
    public function getCategory(): CategoryEntity
    {
        return $this->object;
    }
}
