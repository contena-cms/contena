<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ContextTokenResponse;
use Contena\Core\System\Member\MemberEntity;

/**
 * This route is used to change the password of a logged-in user
 * The required fields are: "password", "newPassword" and "newPasswordConfirm"
 */
abstract class AbstractChangePasswordRoute
{
    abstract public function getDecorated(): AbstractChangePasswordRoute;

    abstract public function change(RequestDataBag $requestDataBag, ChannelContext $context, MemberEntity $member): ContextTokenResponse;
}
