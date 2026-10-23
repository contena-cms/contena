<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\Channel\StoreApiResponse;
use Contena\Core\System\User\UserEntity;

/**
 * @extends StoreApiResponse<UserEntity>
 */
class UserResponse extends StoreApiResponse
{
    public function getUser(): UserEntity
    {
        return $this->object;
    }
}
