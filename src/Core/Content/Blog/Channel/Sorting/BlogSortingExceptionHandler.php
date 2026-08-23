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

    public function matchException(\Throwable $exception): ?\Throwable
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*uniq.blog_sorting.url_key\'/', $exception->getMessage())) {
            $key = [];
            preg_match('/Duplicate entry \'(.*)\' for key/', $exception->getMessage(), $key);

            return new DuplicateBlogSortingKeyException($key[1] ?? '', $exception);
        }

        return null;
    }
}
