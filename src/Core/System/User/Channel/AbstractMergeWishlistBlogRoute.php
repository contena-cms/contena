<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\User\UserEntity;

/**
 * This route can be used to merge wishlist blogs from guest users to registered users.
 */
abstract class AbstractMergeWishlistBlogRoute
{
    abstract public function getDecorated(): AbstractMergeWishlistBlogRoute;

    abstract public function merge(RequestDataBag $data, ChannelContext $context, UserEntity $user): SuccessResponse;
}
