<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\Channel;

use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<LandingPageEntity>
 */
class LandingPageRouteResponse extends StoreApiResponse
{
    public function getLandingPage(): LandingPageEntity
    {
        return $this->object;
    }
}
