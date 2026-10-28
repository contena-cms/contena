<?php declare(strict_types=1);

namespace Contena\Core\Content\LandingPage\SalesChannel;

use Contena\Core\Content\LandingPage\LandingPageEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<LandingPageEntity>
 */
#[Package('discovery')]
class LandingPageRouteResponse extends StoreApiResponse
{
    public function getLandingPage(): LandingPageEntity
    {
        return $this->object;
    }
}
