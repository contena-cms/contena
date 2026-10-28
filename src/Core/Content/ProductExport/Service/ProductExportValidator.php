<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Service;

use Contena\Core\Content\ProductExport\Error\Error;
use Contena\Core\Content\ProductExport\Error\ErrorCollection;
use Contena\Core\Content\ProductExport\ProductExportEntity;
use Contena\Core\Content\ProductExport\Validator\ValidatorInterface;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductExportValidator implements ProductExportValidatorInterface
{
    /**
     * @internal
     *
     * @param ValidatorInterface[] $validators
     */
    public function __construct(private readonly iterable $validators)
    {
    }

    /**
     * @return list<Error>
     */
    public function validate(ProductExportEntity $productExportEntity, string $productExportContent): array
    {
        $errors = new ErrorCollection();
        foreach ($this->validators as $validator) {
            $validator->validate($productExportEntity, $productExportContent, $errors);
        }

        return array_values($errors->getElements());
    }
}
