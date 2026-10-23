<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

abstract class AbstractUserGroupRegistrationSettingsRoute
{
    abstract public function getDecorated(): AbstractUserGroupRegistrationSettingsRoute;

    abstract public function load(string $userGroupId, ChannelContext $context): UserGroupRegistrationSettingsRouteResponse;
}
