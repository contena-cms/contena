<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\System\Channel\ChannelContext;

abstract class AbstractSiteSettingsRoute
{
    abstract public function getDecorated(): AbstractSiteSettingsRoute;

    abstract public function load(ChannelContext $context): SiteSettingsRouteResponse;
}
