<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('framework')]
abstract class AbstractShopSettingsRoute
{
    abstract public function getDecorated(): AbstractShopSettingsRoute;

    abstract public function load(SalesChannelContext $context): ShopSettingsRouteResponse;
}
