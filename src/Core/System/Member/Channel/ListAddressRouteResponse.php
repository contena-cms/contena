<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<EntitySearchResult<ChannelMemberAddressCollection>>
 */
class ListAddressRouteResponse extends ChannelApiResponse
{
    public function getAddressCollection(): ChannelMemberAddressCollection
    {
        return $this->object->getEntities();
    }
}
