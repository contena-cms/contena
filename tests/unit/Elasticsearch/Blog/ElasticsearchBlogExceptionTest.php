<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Blog;

use OpenSearch\Exception\BadRequestHttpException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Elasticsearch\Blog\ElasticsearchBlogException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ElasticsearchBlogException::class)]
class ElasticsearchBlogExceptionTest extends TestCase
{
    public function testExpectedArray(): void
    {
        $previous = new BadRequestHttpException('test');
        $e = ElasticsearchBlogException::cannotChangeCustomFieldType($previous);

        static::assertSame('One or more custom fields already exist in the index with different types. Please reset the index and rebuild it.', $e->getMessage());
        static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
        static::assertSame(ElasticsearchBlogException::ES_BLOG_CANNOT_CHANGE_CUSTOM_FIELD_TYPE, $e->getErrorCode());
        static::assertSame($previous, $e->getPrevious());
    }

    public function testCannotChangeFieldType(): void
    {
        $previous = new BadRequestHttpException('mapper_parsing_exception');
        $exception = ElasticsearchBlogException::cannotChangeFieldType($previous);

        static::assertSame(ElasticsearchBlogException::ES_BLOG_CANNOT_CHANGE_FIELD_TYPE, $exception->getErrorCode());
        static::assertSame('One or more fields already exist in the index with different types. Please reset the index and rebuild it.', $exception->getMessage());
        static::assertSame($previous, $exception->getPrevious());
    }
}
