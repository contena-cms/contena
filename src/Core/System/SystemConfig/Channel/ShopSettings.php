<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\Framework\Struct\Struct;

/**
 * Sales-channel-resolved subset of the system configuration that headless
 * frontends need to render the shop consistently with the administration
 * settings. Only UI- and validation-relevant, non-sensitive values are exposed.
 *
 * @codeCoverageIgnore
 */
final class ShopSettings extends Struct
{

    public function getApiAlias(): string
    {
        return 'shop_settings';
    }
}
