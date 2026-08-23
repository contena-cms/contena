<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\Member\MemberEntity;

/**
 * This route is used to change the email of a logged-in user
 * The required fields are: "password", "email" and "emailConfirmation"
 */
abstract class AbstractChangeEmailRoute
{
    abstract public function getDecorated(): AbstractChangeEmailRoute;

    abstract public function change(RequestDataBag $requestDataBag, ChannelContext $context, MemberEntity $member): SuccessResponse;
}
