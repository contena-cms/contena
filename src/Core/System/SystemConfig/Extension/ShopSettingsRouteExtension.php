<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Extension;

use Contena\Core\Framework\Extensions\Extension;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\Channel\ShopSettingsRouteResponse;

/**
 * @public
 *
 * @codeCoverageIgnore
 *
 * @extends Extension<ShopSettingsRouteResponse>
 */
final class ShopSettingsRouteExtension extends Extension
{
    public const NAME = 'shop-settings-route.load';

    /**
     * @internal Contena owns the constructor; the properties are public API.
     */
    public function __construct(
        public readonly ChannelContext $context,
    ) {
    }
}
