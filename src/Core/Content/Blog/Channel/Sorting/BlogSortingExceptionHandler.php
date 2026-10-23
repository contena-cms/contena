<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Sorting;

use Contena\Core\Content\Blog\Exception\DuplicateBlogSortingKeyException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;

class BlogSortingExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*uniq.blog_sorting.url_key\'/', $e->getMessage())) {
            $key = [];
            preg_match('/Duplicate entry \'(.*)\' for key/', $e->getMessage(), $key);
            $key = $key[1] ?? '';

            return new DuplicateBlogSortingKeyException($key, $e);
        }

        return null;
    }
}
