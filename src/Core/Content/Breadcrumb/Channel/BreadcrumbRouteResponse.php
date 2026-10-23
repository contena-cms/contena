<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb\Channel;

use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<BreadcrumbCollection>
 */
class BreadcrumbRouteResponse extends StoreApiResponse
{
    public function getBreadcrumbCollection(): BreadcrumbCollection
    {
        return $this->object;
    }
}
