<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Service;

use Contena\Core\Content\ProductExport\ProductExportEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
interface ProductExportRendererInterface
{
    public function renderHeader(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext
    ): string;

    public function renderFooter(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext
    ): string;

    /**
     * @param array<string, mixed> $data
     */
    public function renderBody(
        ProductExportEntity $productExport,
        SalesChannelContext $salesChannelContext,
        array $data
    ): string;
}
