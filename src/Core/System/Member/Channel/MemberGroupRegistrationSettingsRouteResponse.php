<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;

/**
 * @extends ChannelApiResponse<MemberGroupEntity>
 */
class MemberGroupRegistrationSettingsRouteResponse extends ChannelApiResponse
{
    public function getRegistration(): MemberGroupEntity
    {
        return $this->object;
    }
}
