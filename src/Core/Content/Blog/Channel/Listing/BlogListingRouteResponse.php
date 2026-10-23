<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<BlogListingResult>
 */
class BlogListingRouteResponse extends StoreApiResponse
{
    public function getResult(): BlogListingResult
    {
        return $this->object;
    }
}
