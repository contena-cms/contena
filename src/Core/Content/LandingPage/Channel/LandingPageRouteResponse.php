<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Channel;

use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<LandingPageEntity>
 */
class LandingPageRouteResponse extends ChannelApiResponse
{
    public function getLandingPage(): LandingPageEntity
    {
        return $this->object;
    }
}
