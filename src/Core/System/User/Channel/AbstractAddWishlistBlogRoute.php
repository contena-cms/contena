<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;

abstract class AbstractAddWishlistBlogRoute
{
    abstract public function getDecorated(): AbstractAddWishlistBlogRoute;

    abstract public function add(string $blogId, ChannelContext $context, UserEntity $user): SuccessResponse;
}
