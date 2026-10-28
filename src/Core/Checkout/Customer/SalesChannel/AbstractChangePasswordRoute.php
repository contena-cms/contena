<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\System\SalesChannel\ContextTokenResponse;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route is used to change the password of a logged-in user
 * The required fields are: "password", "newPassword" and "newPasswordConfirm"
 */
#[Package('checkout')]
abstract class AbstractChangePasswordRoute
{
    abstract public function getDecorated(): AbstractChangePasswordRoute;

    abstract public function change(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer): ContextTokenResponse;
}
