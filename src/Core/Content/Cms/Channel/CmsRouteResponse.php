<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Channel;

use Contena\Core\Content\Cms\CmsPageEntity;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<CmsPageEntity>
 */
class CmsRouteResponse extends ChannelApiResponse
{
    public function getCmsPage(): CmsPageEntity
    {
        return $this->object;
    }
}
