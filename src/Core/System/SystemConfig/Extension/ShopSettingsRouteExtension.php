<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SystemConfig\SalesChannel\ShopSettingsRouteResponse;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ShopSettingsRouteResponse>
 */
#[Package('framework')]
final class ShopSettingsRouteExtension extends Extension
{
    public const NAME = 'shop-settings-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly SalesChannelContext $context,
    ) {
    }
}
