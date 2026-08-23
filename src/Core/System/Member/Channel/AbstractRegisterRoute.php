<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\Channel\ChannelContext;

/**
 * This route is used for member registration
 * The required parameters are: "name", "email", "password" and "frontendUrl".
 */
abstract class AbstractRegisterRoute
{
    abstract public function getDecorated(): AbstractRegisterRoute;

    abstract public function register(RequestDataBag $data, ChannelContext $context, bool $validateFrontendUrl = true, ?DataValidationDefinition $additionalValidationDefinitions = null): MemberResponse;
}
