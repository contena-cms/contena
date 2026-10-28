<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Service;

use Contena\Core\Content\ProductExport\ProductExportEntity;
use Contena\Core\Content\ProductExport\Struct\ExportBehavior;
use Contena\Core\Content\ProductExport\Struct\ProductExportResult;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductExportGeneratorInterface
{
    public function generate(
        ProductExportEntity $productExport,
        ExportBehavior $exportBehavior
    ): ?ProductExportResult;
}
