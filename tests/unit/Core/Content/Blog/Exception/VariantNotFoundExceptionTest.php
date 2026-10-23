<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Exception\VariantNotFoundException;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(VariantNotFoundException::class)]
class VariantNotFoundExceptionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $ids = new IdsCollection();

        $options = [
            $ids->get('groupId1') => $ids->get('optionId1'),
            $ids->get('groupId1') => $ids->get('optionId2'),
            $ids->get('groupId2') => $ids->get('optionId3'),
        ];

        $exception = new VariantNotFoundException($ids->get('blogId'), $options);

        static::assertSame('CONTENT__BLOG_VARIANT_NOT_FOUND', $exception->getErrorCode());
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(
            'Variant for blogId ' . $ids->get('blogId') . ' with options ' . \json_encode($options, \JSON_THROW_ON_ERROR) . ' not found.',
            $exception->getMessage()
        );
    }
}
