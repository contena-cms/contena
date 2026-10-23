<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Suggest;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<BlogListingResult>
 */
class BlogSuggestRouteResponse extends StoreApiResponse
{
    public function getListingResult(): BlogListingResult
    {
        return $this->object;
    }
}
