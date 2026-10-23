<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<ShopSettings>
 *
 * @codeCoverageIgnore
 */
class ShopSettingsRouteResponse extends ChannelApiResponse
{
    public function getSettings(): ShopSettings
    {
        return $this->object;
    }
}
