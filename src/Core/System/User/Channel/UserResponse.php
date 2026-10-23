<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\StoreApiResponse;

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
