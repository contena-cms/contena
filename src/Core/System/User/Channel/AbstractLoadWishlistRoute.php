<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractLoadWishlistRoute
{
    abstract public function getDecorated(): AbstractLoadWishlistRoute;

    abstract public function load(Request $request, ChannelContext $context, Criteria $criteria, UserEntity $user): LoadWishlistRouteResponse;
}
