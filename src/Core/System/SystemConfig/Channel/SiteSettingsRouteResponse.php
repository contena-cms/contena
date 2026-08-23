<?php declare(strict_types=1);

namespace Contena\Core\System\SystemConfig\Channel;

use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<SiteSettings>
 *
 * @codeCoverageIgnore
 */
class SiteSettingsRouteResponse extends ChannelApiResponse
{
    public function getSettings(): SiteSettings
    {
        return $this->object;
    }
}
