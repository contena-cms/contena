<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Search;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<BlogListingResult>
 */
class BlogSearchRouteResponse extends ChannelApiResponse
{
    public function getListingResult(): BlogListingResult
    {
        return $this->object;
    }
}
