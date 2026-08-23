<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Channel;

use Contena\Core\Content\Sitemap\Struct\SitemapCollection;
use Contena\Core\System\Channel\ChannelApiResponse;

/**
 * @extends ChannelApiResponse<SitemapCollection>
 */
class SitemapRouteResponse extends ChannelApiResponse
{
    public function getSitemaps(): SitemapCollection
    {
        return $this->object;
    }
}
