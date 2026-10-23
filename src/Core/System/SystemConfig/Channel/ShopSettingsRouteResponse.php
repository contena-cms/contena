<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ShopSettings>
 *
 * @codeCoverageIgnore
 */
class ShopSettingsRouteResponse extends StoreApiResponse
{
    public function getSettings(): ShopSettings
    {
        return $this->object;
    }
}
