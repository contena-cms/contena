<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Error\Error;
use Contena\Core\Content\Blog\Cart\BlogNotFoundError;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(BlogNotFoundError::class)]
class BlogNotFoundErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $id = Uuid::randomHex();
        $error = new BlogNotFoundError($id);

        static::assertSame('The blog %s could not be found', $error->getMessage());
        static::assertSame('blog-not-found' . $id, $error->getId());
        static::assertSame('blog-not-found', $error->getMessageKey());
        static::assertSame(Error::LEVEL_ERROR, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertTrue($error->isPersistent());
        static::assertSame(['id' => $id], $error->getParameters());
    }
}
