<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\User\UserEntity;

/**
 * This route can be used to change profile information about the logged-in admin_user
 * The required fields are "salutationId", "firstName" and "lastName"
 */
abstract class AbstractChangeUserProfileRoute
{
    abstract public function getDecorated(): AbstractChangeUserProfileRoute;

    abstract public function change(RequestDataBag $data, ChannelContext $context, UserEntity $user): SuccessResponse;
}
