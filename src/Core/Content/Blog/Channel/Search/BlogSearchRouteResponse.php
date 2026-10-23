<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Search;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<BlogListingResult>
 */
class BlogSearchRouteResponse extends StoreApiResponse
{
    public function getListingResult(): BlogListingResult
    {
        return $this->object;
    }
}
