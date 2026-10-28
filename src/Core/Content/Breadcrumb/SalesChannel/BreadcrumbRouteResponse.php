<?php declare(strict_types=1);

namespace Contena\Core\Content\Breadcrumb\SalesChannel;

use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<BreadcrumbCollection>
 */
#[Package('inventory')]
class BreadcrumbRouteResponse extends StoreApiResponse
{
    public function getBreadcrumbCollection(): BreadcrumbCollection
    {
        return $this->object;
    }
}
