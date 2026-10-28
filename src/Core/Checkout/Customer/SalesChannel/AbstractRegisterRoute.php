<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * This route is used for customer registration
 * The required parameters are: "salutationId", "firstName", "lastName", "email", "password" and "billingAddress"
 * "storefrontUrl" is additionally required when double opt-in registration is enabled
 * The "billingAddress" should has required parameters: "salutationId", "firstName", "lastName", "street", "zipcode", "city", "countyId".
 */
#[Package('checkout')]
abstract class AbstractRegisterRoute
{
    abstract public function getDecorated(): AbstractRegisterRoute;

    abstract public function register(RequestDataBag $data, SalesChannelContext $context, bool $validateStorefrontUrl = true, ?DataValidationDefinition $additionalValidationDefinitions = null): CustomerResponse;
}
