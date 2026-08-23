<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;

/**
 * This route is used handle the password reset form
 * The required parameters are: "hash" (received from the mail), "newPassword" and "newPasswordConfirm"
 */
abstract class AbstractResetPasswordRoute
{
    abstract public function getDecorated(): AbstractResetPasswordRoute;

    abstract public function resetPassword(RequestDataBag $data, ChannelContext $context): SuccessResponse;
}
