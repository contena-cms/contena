<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<CategoryEntity>
 */
class CategoryRouteResponse extends ChannelApiResponse
{
    public function getCategory(): CategoryEntity
    {
        return $this->object;
    }
}
