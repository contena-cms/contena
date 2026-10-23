<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CategoryEntity>
 */
class CategoryRouteResponse extends StoreApiResponse
{
    public function getCategory(): CategoryEntity
    {
        return $this->object;
    }
}
