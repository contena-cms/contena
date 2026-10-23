<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\ConfigHandler;

use Contena\Core\Framework\Log\Package;

interface ConfigHandlerInterface
{
    /**
     * @return array<string, array<array<string, mixed>>>
     */
    public function getSitemapConfig(): array;
}
