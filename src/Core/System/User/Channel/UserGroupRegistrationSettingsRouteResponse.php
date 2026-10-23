<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;

/**
 * @extends ChannelApiResponse<UserGroupEntity>
 */
class UserGroupRegistrationSettingsRouteResponse extends ChannelApiResponse
{
    public function getRegistration(): UserGroupEntity
    {
        return $this->object;
    }
}
