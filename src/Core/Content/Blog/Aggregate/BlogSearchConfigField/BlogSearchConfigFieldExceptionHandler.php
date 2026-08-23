<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField;

use Contena\Core\Content\Blog\Exception\DuplicateBlogSearchConfigFieldException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;

class BlogSearchConfigFieldExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*uniq.blog_search_config_field.field_config_id\'/', $e->getMessage())) {
            $field = [];
            preg_match('/Duplicate entry \'(.*)\' for key/', $e->getMessage(), $field);
            $fieldNameMatch = $field[1] ?? '';
            $field = substr($fieldNameMatch, 0, (int) strpos($fieldNameMatch, '-'));

            return new DuplicateBlogSearchConfigFieldException($field, $e);
        }

        return null;
    }
}
