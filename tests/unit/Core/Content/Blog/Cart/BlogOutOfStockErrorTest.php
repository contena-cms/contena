<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Error\Error;
use Contena\Core\Content\Blog\Cart\BlogOutOfStockError;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(BlogOutOfStockError::class)]
class BlogOutOfStockErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $id = Uuid::randomHex();
        $error = new BlogOutOfStockError(id: $id, name: 'Shirt');

        static::assertSame('The blog Shirt is no longer available', $error->getMessage());
        static::assertSame('blog-out-of-stock' . $id, $error->getId());
        static::assertSame('blog-out-of-stock', $error->getMessageKey());
        static::assertSame(Error::LEVEL_ERROR, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertTrue($error->isPersistent());
        static::assertSame(['name' => 'Shirt'], $error->getParameters());
        static::assertSame('Shirt', $error->getName());
    }
}
