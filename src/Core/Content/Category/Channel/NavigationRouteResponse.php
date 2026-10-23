<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Channel;

use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<CategoryCollection>
 */
class NavigationRouteResponse extends ChannelApiResponse
{
    public function getCategories(): CategoryCollection
    {
        return $this->object;
    }
}
