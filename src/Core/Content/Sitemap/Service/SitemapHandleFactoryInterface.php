<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Service;

use League\Flysystem\FilesystemOperator;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('discovery')]
interface SitemapHandleFactoryInterface
{
    public function create(
        FilesystemOperator $filesystem,
        SalesChannelContext $context,
        ?string $domain = null,
        ?string $domainId = null,
    ): SitemapHandleInterface;
}
