<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\Channel\StoreApiResponse;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;

/**
 * @extends StoreApiResponse<UserGroupEntity>
 */
class UserGroupRegistrationSettingsRouteResponse extends StoreApiResponse
{
    public function getRegistration(): UserGroupEntity
    {
        return $this->object;
    }
}
