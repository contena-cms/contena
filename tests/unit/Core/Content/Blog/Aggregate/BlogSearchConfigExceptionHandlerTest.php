<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Aggregate;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigExceptionHandler;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfigField\BlogSearchConfigFieldExceptionHandler;
use Contena\Core\Content\Blog\Exception\DuplicateBlogSearchConfigFieldException;
use Contena\Core\Content\Blog\Exception\DuplicateBlogSearchConfigLanguageException;

/**
 * @internal
 */
#[CoversClass(BlogSearchConfigExceptionHandler::class)]
#[CoversClass(BlogSearchConfigFieldExceptionHandler::class)]
class BlogSearchConfigExceptionHandlerTest extends TestCase
{
    public function testDuplicateLanguageIsTranslated(): void
    {
        $exception = new BlogSearchConfigExceptionHandler()->matchException(new \RuntimeException(
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'tenant-language\' for key \'uniq.blog_search_config.tenant_id_language_id\''
        ));

        static::assertInstanceOf(DuplicateBlogSearchConfigLanguageException::class, $exception);
        static::assertSame('CONTENT__DUPLICATE_BLOG_SEARCH_CONFIG_LANGUAGE_ID', $exception->getErrorCode());
    }

    public function testDuplicateFieldIsTranslatedForBlogIndex(): void
    {
        $exception = new BlogSearchConfigFieldExceptionHandler()->matchException(new \RuntimeException(
            'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'name-config\' for key \'uniq.blog_search_config_field.field_config_id\''
        ));

        static::assertInstanceOf(DuplicateBlogSearchConfigFieldException::class, $exception);
        static::assertSame('CONTENT__DUPLICATE_BLOG_SEARCH_CONFIG_FIELD', $exception->getErrorCode());
    }
}
