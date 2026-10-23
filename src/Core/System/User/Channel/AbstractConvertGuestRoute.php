<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Framework\Validation\DataValidationDefinition;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\User\UserEntity;

/**
 * This route is used to set a password for a guest admin user and convert it to a registered one.
 */
abstract class AbstractConvertGuestRoute
{
    abstract public function getDecorated(): AbstractConvertGuestRoute;

    abstract public function convertGuest(RequestDataBag $requestDataBag, ChannelContext $context, UserEntity $user, ?DataValidationDefinition $additionalValidationDefinitions = null): SuccessResponse;
}
