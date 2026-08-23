<?php declare(strict_types=1);

namespace Contena\Core\System\Region\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\Region\RegionCollection;

/**
 * @extends ChannelApiResponse<EntitySearchResult<RegionCollection>>
 */
class RegionRouteResponse extends ChannelApiResponse
{
    public function getRegions(): RegionCollection
    {
        return $this->object->getEntities();
    }
}
