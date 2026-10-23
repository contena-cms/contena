<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\Channel\ChannelContext;

/**
 * This route is used for user registration
 * The required parameters are: "salutationId", "firstName", "lastName", "email", "password" and "billingAddress"
 * "frontendUrl" is additionally required when double opt-in registration is enabled
 * The "billingAddress" should has required parameters: "salutationId", "firstName", "lastName", "street", "zipcode", "city", "countyId".
 */
abstract class AbstractRegisterRoute
{
    abstract public function getDecorated(): AbstractRegisterRoute;

    abstract public function register(RequestDataBag $data, ChannelContext $context, bool $validateFrontendUrl = true, ?DataValidationDefinition $additionalValidationDefinitions = null): UserResponse;
}
