<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SalesChannel\SuccessResponse;

/**
 * This route is used to set a password for a guest user and convert it to a registered one.
 */
#[Package('checkout')]
abstract class AbstractConvertGuestRoute
{
    abstract public function getDecorated(): AbstractConvertGuestRoute;

    abstract public function convertGuest(RequestDataBag $requestDataBag, SalesChannelContext $context, CustomerEntity $customer, ?DataValidationDefinition $additionalValidationDefinitions = null): SuccessResponse;
}
