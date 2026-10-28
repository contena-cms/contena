<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Validator;

use Contena\Core\Content\ProductExport\Error\ErrorCollection;
use Contena\Core\Content\ProductExport\ProductExportEntity;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
interface ValidatorInterface
{
    public function validate(ProductExportEntity $productExportEntity, string $productExportContent, ErrorCollection $errors): void;
}
