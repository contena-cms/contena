<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\SalesChannel;

use Contena\Core\Content\Sitemap\Struct\SitemapCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\StoreApiResponse;

/**
 * @extends StoreApiResponse<SitemapCollection>
 */
#[Package('discovery')]
class SitemapRouteResponse extends StoreApiResponse
{
    public function getSitemaps(): SitemapCollection
    {
        return $this->object;
    }
}
