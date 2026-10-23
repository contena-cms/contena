<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\Channel\ChannelApiResponse;
use Contena\Core\System\User\UserEntity;

/**
 * @extends ChannelApiResponse<UserEntity>
 */
class UserResponse extends ChannelApiResponse
{
    public function getUser(): UserEntity
    {
        return $this->object;
    }
}
