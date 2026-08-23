<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Suggest;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<BlogListingResult>
 */
class BlogSuggestRouteResponse extends ChannelApiResponse
{
    public function getListingResult(): BlogListingResult
    {
        return $this->object;
    }
}
