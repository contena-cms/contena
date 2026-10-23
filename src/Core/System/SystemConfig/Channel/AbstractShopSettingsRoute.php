<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

abstract class AbstractShopSettingsRoute
{
    abstract public function getDecorated(): AbstractShopSettingsRoute;

    abstract public function load(ChannelContext $context): ShopSettingsRouteResponse;
}
