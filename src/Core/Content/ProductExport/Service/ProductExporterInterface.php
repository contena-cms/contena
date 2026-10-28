<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Service;

use Contena\Core\Content\ProductExport\Exception\ExportInvalidException;
use Contena\Core\Content\ProductExport\Exception\ExportNotFoundException;
use Contena\Core\Content\ProductExport\Struct\ExportBehavior;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
interface ProductExporterInterface
{
    /**
     * @throws ExportInvalidException
     * @throws ExportNotFoundException
     */
    public function export(
        SalesChannelContext $context,
        ExportBehavior $behavior,
        ?string $productExportId = null
    ): void;
}
