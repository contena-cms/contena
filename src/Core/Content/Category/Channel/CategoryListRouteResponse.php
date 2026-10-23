<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<EntitySearchResult<CategoryCollection>>
 */
class CategoryListRouteResponse extends StoreApiResponse
{
    public function getCategories(): CategoryCollection
    {
        return $this->object->getEntities();
    }
}
