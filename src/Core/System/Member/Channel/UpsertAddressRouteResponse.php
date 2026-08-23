<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\Member\Aggregate\MemberAddress\MemberAddressEntity;

/**
 * @extends ChannelApiResponse<MemberAddressEntity>
 */
class UpsertAddressRouteResponse extends ChannelApiResponse
{
    public function getAddress(): MemberAddressEntity
    {
        return $this->object;
    }
}
