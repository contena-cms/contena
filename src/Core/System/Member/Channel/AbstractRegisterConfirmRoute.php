<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\Channel\ChannelContext;

/**
 * This route can be used to complete the double optin registration.
 * The required parameters are: "hash" (received from the mail) and "em" (received from the mail)
 */
abstract class AbstractRegisterConfirmRoute
{
    abstract public function getDecorated(): AbstractRegisterConfirmRoute;

    abstract public function confirm(RequestDataBag $dataBag, ChannelContext $context): MemberResponse;
}
