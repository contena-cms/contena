<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Channel;

use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<CmsPageEntity>
 */
class CmsRouteResponse extends StoreApiResponse
{
    public function getCmsPage(): CmsPageEntity
    {
        return $this->object;
    }
}
