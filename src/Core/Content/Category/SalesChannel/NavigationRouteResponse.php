<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\SalesChannel;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CategoryCollection>
 */
#[Package('discovery')]
class NavigationRouteResponse extends StoreApiResponse
{
    public function getCategories(): CategoryCollection
    {
        return $this->object;
    }
}
