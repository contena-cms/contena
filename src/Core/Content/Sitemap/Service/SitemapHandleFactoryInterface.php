<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use Contena\Core\System\Channel\ChannelContext;

interface SitemapHandleFactoryInterface
{
    public function create(
        FilesystemOperator $filesystem,
        ChannelContext $context,
        ?string $domain = null,
        ?string $domainId = null,
    ): SitemapHandleInterface;
}
