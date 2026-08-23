<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\ConfigHandler;

interface ConfigHandlerInterface
{
    /**
     * @return array<string, array<array<string, mixed>>>
     */
    public function getSitemapConfig(): array;
}
