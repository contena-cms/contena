<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Service;

use Contena\Core\Content\ProductExport\Error\Error;
use Contena\Core\Content\ProductExport\ProductExportEntity;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
interface ProductExportValidatorInterface
{
    /**
     * @return list<Error>
     */
    public function validate(ProductExportEntity $productExportEntity, string $productExportContent): array;
}
