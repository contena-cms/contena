<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\User\UserEntity;

abstract class AbstractRemoveWishlistBlogRoute
{
    abstract public function getDecorated(): AbstractRemoveWishlistBlogRoute;

    abstract public function delete(string $blogId, ChannelContext $context, UserEntity $user): SuccessResponse;
}
