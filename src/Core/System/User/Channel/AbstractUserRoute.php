<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\HttpFoundation\Request;

/**
 * This route is used to get information about the current logged-in user
 */
abstract class AbstractUserRoute
{
    abstract public function getDecorated(): AbstractUserRoute;

    abstract public function load(Request $request, ChannelContext $context, Criteria $criteria, UserEntity $user): UserResponse;
}
