<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Service;

use Contena\Core\Content\Sitemap\Exception\AlreadyLockedException;
use Contena\Core\Content\Sitemap\Struct\SitemapGenerationResult;
use Contena\Core\System\Channel\ChannelContext;

interface SitemapExporterInterface
{
    public const int SITEMAP_URL_LIMIT = 49999;

    public const int STRATEGY_MANUAL = 1;
    public const int STRATEGY_SCHEDULED_TASK = 2;
    public const int STRATEGY_LIVE = 3;

    /**
     * @throws AlreadyLockedException
     */
    public function generate(ChannelContext $context, bool $force = false, ?string $lastProvider = null, ?int $offset = null): SitemapGenerationResult;
}
