<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\SalesChannel;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<ShopSettings>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ShopSettingsRouteResponse extends StoreApiResponse
{
    public function getSettings(): ShopSettings
    {
        return $this->object;
    }
}
