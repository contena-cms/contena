<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\SuccessResponse;

/**
 * This route is used to send a password recovery mail
 * The required parameters are: "email" and "storefrontUrl"
 * The process can be completed with the hash in the Route Contena\Core\Checkout\Customer\SalesChannel\AbstractResetPasswordRoute
 */
#[Package('checkout')]
abstract class AbstractSendPasswordRecoveryMailRoute
{
    abstract public function getDecorated(): AbstractSendPasswordRecoveryMailRoute;

    abstract public function sendRecoveryMail(RequestDataBag $data, SalesChannelContext $context, bool $validateStorefrontUrl = true): SuccessResponse;
}
