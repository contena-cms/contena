<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Sitemap;

use Contena\Core\Content\Sitemap\Struct\Sitemap;
use Contena\Core\Framework\Struct\Struct;

class SitemapPage extends Struct
{
    /**
     * @var array<Sitemap>
     */
    protected array $sitemaps;

    /**
     * @return array<Sitemap>
     */
    public function getSitemaps(): array
    {
        return $this->sitemaps;
    }

    /**
     * @param array<Sitemap> $sitemaps
     */
    public function setSitemaps(array $sitemaps): void
    {
        $this->sitemaps = $sitemaps;
    }
}
