<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Sorting;

use Contena\Core\Content\Product\Exception\DuplicateProductSortingKeyException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductSortingExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*uniq.product_sorting.url_key\'/', $e->getMessage())) {
            $key = [];
            preg_match('/Duplicate entry \'(.*)\' for key/', $e->getMessage(), $key);
            $key = $key[1] ?? '';

            return new DuplicateProductSortingKeyException($key, $e);
        }

        return null;
    }
}
