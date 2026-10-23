<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Service;

use Contena\Core\Content\Sitemap\Struct\Sitemap;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

interface SitemapListerInterface
{
    /**
     * @return Sitemap[]
     */
    public function getSitemaps(ChannelContext $channelContext): array;
}
