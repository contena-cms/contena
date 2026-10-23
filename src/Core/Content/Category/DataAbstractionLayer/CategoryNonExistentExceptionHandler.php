<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\DataAbstractionLayer;

use Contena\Core\Content\Category\CategoryException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;

class CategoryNonExistentExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1452 Cannot add or update a child row: a foreign key constraint fails.*category\.after_category_id/', $e->getMessage())) {
            return CategoryException::afterCategoryNotFound();
        }

        return null;
    }
}
