<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Service;

use Contena\Core\System\Channel\ChannelContext;
use League\Flysystem\FilesystemOperator;

interface SitemapHandleFactoryInterface
{
    public function create(
        FilesystemOperator $filesystem,
        ChannelContext $context,
        ?string $domain = null,
        ?string $domainId = null,
    ): SitemapHandleInterface;
}
