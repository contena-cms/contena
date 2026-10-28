<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Service;

use Contena\Core\Content\ProductExport\ProductExportEntity;
use Contena\Core\Content\ProductExport\Struct\ExportBehavior;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductExportFileHandlerInterface
{
    public function getFilePath(ProductExportEntity $productExport, bool $partialGeneration = false): string;

    public function writeProductExportContent(string $content, string $filePath, bool $append = false): bool;

    public function isValidFile(string $filePath, ExportBehavior $behavior, ProductExportEntity $productExport): bool;

    public function finalizePartialProductExport(string $partialFilePath, string $finalFilePath, string $headerContent, string $footerContent): bool;
}
