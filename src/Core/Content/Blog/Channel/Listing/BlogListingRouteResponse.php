<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing;

use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<BlogListingResult>
 */
class BlogListingRouteResponse extends ChannelApiResponse
{
    public function getResult(): BlogListingResult
    {
        return $this->object;
    }
}
