<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\SalesChannel;

use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CmsPageEntity>
 */
#[Package('discovery')]
class CmsRouteResponse extends StoreApiResponse
{
    public function getCmsPage(): CmsPageEntity
    {
        return $this->object;
    }
}
