<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Breadcrumb;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Exception\BlogNotFoundException;
use Contena\Core\Content\Breadcrumb\BreadcrumbException;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(BreadcrumbException::class)]
class BreadcrumbExceptionTest extends TestCase
{
    public function testCategoryNotFoundForBlogReturnsCorrectException(): void
    {
        $exception = BreadcrumbException::categoryNotFoundForBlog('invalidBlogId');

        static::assertSame(Response::HTTP_NO_CONTENT, $exception->getStatusCode());
        static::assertSame('BREADCRUMB_CATEGORY_NOT_FOUND', $exception->getErrorCode());
        static::assertSame('The main category for blog invalidBlogId is not found', $exception->getMessage());
    }

    public function testCategoryNotFoundReturnsCorrectException(): void
    {
        $exception = BreadcrumbException::categoryNotFound('invalidId');

        static::assertInstanceOf(CategoryNotFoundException::class, $exception);
        static::assertSame('CONTENT__CATEGORY_NOT_FOUND', $exception->getErrorCode());
    }

    public function testBlogNotFoundReturnsCorrectException(): void
    {
        $exception = BreadcrumbException::blogNotFound('invalidId');

        static::assertInstanceOf(BlogNotFoundException::class, $exception);
        static::assertSame('BLOG_BLOG_NOT_FOUND', $exception->getErrorCode());
    }
}
