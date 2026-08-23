<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogSearchConfig;

use Contena\Core\Content\Blog\Exception\DuplicateBlogSearchConfigLanguageException;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerInterface;

class BlogSearchConfigExceptionHandler implements ExceptionHandlerInterface
{
    public function getPriority(): int
    {
        return ExceptionHandlerInterface::PRIORITY_DEFAULT;
    }

    public function matchException(\Throwable $e): ?\Throwable
    {
        if (preg_match('/SQLSTATE\[23000\]:.*1062 Duplicate.*uniq.blog_search_config.tenant_id_language_id\'/', $e->getMessage())) {
            return new DuplicateBlogSearchConfigLanguageException('', $e);
        }

        return null;
    }
}
