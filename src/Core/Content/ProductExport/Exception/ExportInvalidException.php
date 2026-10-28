<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Exception;

use Contena\Core\Content\ProductExport\Error\Error;
use Contena\Core\Content\ProductExport\Error\ErrorMessage;
use Contena\Core\Content\ProductExport\ProductExportEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\ContenaHttpException;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ExportInvalidException extends ContenaHttpException
{
    /**
     * @var ErrorMessage[]
     */
    protected array $errorMessages;

    /**
     * @param Error[] $errors
     */
    public function __construct(
        ProductExportEntity $productExportEntity,
        array $errors
    ) {
        $errorMessages = array_merge(
            ...array_map(
                static fn (Error $error) => $error->getErrorMessages(),
                $errors
            )
        );

        $this->errorMessages = $errorMessages;

        parent::__construct(
            \sprintf(
                'Export file generation for product export %s (%s) resulted in validation errors',
                $productExportEntity->getId(),
                $productExportEntity->getFileName()
            ),
            ['errors' => $errors, 'errorMessages' => $errorMessages]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__PRODUCT_EXPORT_INVALID_CONTENT';
    }

    /**
     * @return ErrorMessage[]
     */
    public function getErrorMessages(): array
    {
        return $this->errorMessages;
    }
}
