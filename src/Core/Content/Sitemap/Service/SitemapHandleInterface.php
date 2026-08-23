<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Service;

use Contena\Core\Content\Sitemap\Struct\Url;

interface SitemapHandleInterface
{
    /**
     * @param list<Url> $urls
     */
    public function write(array $urls): void;

    public function finish(): void;
}
