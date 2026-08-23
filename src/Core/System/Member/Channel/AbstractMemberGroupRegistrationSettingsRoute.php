<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Channel;

use Contena\Core\System\Channel\ChannelContext;

abstract class AbstractMemberGroupRegistrationSettingsRoute
{
    abstract public function getDecorated(): AbstractMemberGroupRegistrationSettingsRoute;

    abstract public function load(string $memberGroupId, ChannelContext $context): MemberGroupRegistrationSettingsRouteResponse;
}
