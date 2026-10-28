<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\Aggregate\ProductSearchConfig;

use Contena\Core\Content\Product\Exception\DuplicateProductSearchConfigLanguageException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductSearchConfigExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*uniq.product_search_config.language_id\'/', $e->getMessage())) {
            return new DuplicateProductSearchConfigLanguageException('', $e);
        }

        return null;
    }
}
