<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(BlogException::class)]
class BlogExceptionTest extends TestCase
{
    public function testInvalidCheapestPriceFacade(): void
    {
        $blogId = 'blog-id-1';

        $exception = BlogException::invalidCheapestPriceFacade($blogId);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(BlogException::BLOG_INVALID_CHEAPEST_PRICE_FACADE, $exception->getErrorCode());
        static::assertSame('Cheapest price facade for blog blog-id-1 is invalid', $exception->getMessage());
        static::assertSame(['id' => $blogId], $exception->getParameters());
    }

    public function testSortingNotFound(): void
    {
        $key = 'value';

        $exception = BlogException::sortingNotFoundException($key);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(BlogException::SORTING_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find sorting with key "value"', $exception->getMessage());
        static::assertSame(['entity' => 'sorting', 'field' => 'key', 'value' => $key], $exception->getParameters());
    }

    public function testInvalidPriceDefinition(): void
    {
        $exception = BlogException::invalidPriceDefinition();

        static::assertSame(Response::HTTP_CONFLICT, $exception->getStatusCode());
        static::assertSame(BlogException::BLOG_INVALID_PRICE_DEFINITION_CODE, $exception->getErrorCode());
        static::assertSame('Provided price definition is invalid.', $exception->getMessage());
    }

    public function testProxyManipulationNotAllowed(): void
    {
        $property = 'property';

        $exception = BlogException::proxyManipulationNotAllowed($property);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(BlogException::BLOG_PROXY_MANIPULATION_NOT_ALLOWED_CODE, $exception->getErrorCode());
        static::assertSame('Manipulation of pricing proxy field property is not allowed', $exception->getMessage());
        static::assertSame(['property' => $property], $exception->getParameters());
    }

    public function testBlogNotFound(): void
    {
        $blogId = 'blog-id';

        $exception = BlogException::blogNotFound($blogId);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(BlogException::BLOG_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find blog with id "blog-id"', $exception->getMessage());
        static::assertSame(['entity' => 'blog', 'field' => 'id', 'value' => $blogId], $exception->getParameters());
    }

    public function testCategoryNotFound(): void
    {
        $categoryId = 'category-id';

        $exception = BlogException::categoryNotFound($categoryId);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(BlogException::CATEGORY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find category with id "category-id"', $exception->getMessage());
        static::assertSame(['entity' => 'category', 'field' => 'id', 'value' => $categoryId], $exception->getParameters());
    }

    public function testConfigurationOptionAlreadyExists(): void
    {
        $exception = BlogException::configurationOptionAlreadyExists();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(BlogException::BLOG_CONFIGURATION_OPTION_ALREADY_EXISTS, $exception->getErrorCode());
        static::assertSame('Configuration option already exists', $exception->getMessage());
    }

    public function testInvalidOptionsParameter(): void
    {
        $exception = BlogException::invalidOptionsParameter();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(BlogException::BLOG_INVALID_OPTIONS_PARAMETER, $exception->getErrorCode());
        static::assertSame('The parameter options is invalid.', $exception->getMessage());
    }
}
