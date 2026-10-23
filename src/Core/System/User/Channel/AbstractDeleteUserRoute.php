<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\NoContentResponse;
use Contena\Core\System\User\UserEntity;

/**
 * This route can be used to delete a user
 */
abstract class AbstractDeleteUserRoute
{
    abstract public function getDecorated(): AbstractDeleteUserRoute;

    abstract public function delete(ChannelContext $context, UserEntity $user): NoContentResponse;
}
