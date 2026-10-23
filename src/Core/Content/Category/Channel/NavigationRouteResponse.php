<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CategoryCollection>
 */
class NavigationRouteResponse extends StoreApiResponse
{
    public function getCategories(): CategoryCollection
    {
        return $this->object;
    }
}
