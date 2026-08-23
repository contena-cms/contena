<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;

/**
 * This route is used to send a password recovery mail
 * The required parameters are: "email" and "frontendUrl".
 * The process can be completed with the hash in the Route Contena\Core\System\Member\Channel\AbstractResetPasswordRoute
 */
abstract class AbstractSendPasswordRecoveryMailRoute
{
    abstract public function getDecorated(): AbstractSendPasswordRecoveryMailRoute;

    abstract public function sendRecoveryMail(RequestDataBag $data, ChannelContext $context, bool $validateFrontendUrl = true): SuccessResponse;
}
