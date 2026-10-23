<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Channel;

use Contena\Core\Content\Sitemap\Struct\SitemapCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\StoreApiResponse;

/**
 * @extends StoreApiResponse<SitemapCollection>
 */
class SitemapRouteResponse extends StoreApiResponse
{
    public function getSitemaps(): SitemapCollection
    {
        return $this->object;
    }
}
