<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Error\Error;
use Contena\Core\Content\Blog\Cart\BlogStockReachedError;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(BlogStockReachedError::class)]
class BlogStockReachedErrorTest extends TestCase
{
    public function testConstructDefaultsToResolved(): void
    {
        $id = Uuid::randomHex();
        $error = new BlogStockReachedError(id: $id, name: 'Shirt', quantity: 3);

        static::assertSame('The blog Shirt is only available 3 times', $error->getMessage());
        static::assertSame('blog-stock-reached' . $id, $error->getId());
        static::assertSame('blog-stock-reached', $error->getMessageKey());
        static::assertSame(Error::LEVEL_WARNING, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertTrue($error->isPersistent());
        static::assertSame(['name' => 'Shirt', 'quantity' => 3], $error->getParameters());
        static::assertSame('Shirt', $error->getName());
        static::assertSame(3, $error->getQuantity());
    }

    #[DataProvider('resolvedProvider')]
    public function testResolvedControlsLevelAndPersistence(bool $resolved, int $expectedLevel): void
    {
        $error = new BlogStockReachedError(
            id: Uuid::randomHex(),
            name: 'Shirt',
            quantity: 3,
            resolved: $resolved,
        );

        static::assertSame($expectedLevel, $error->getLevel());
        static::assertSame($resolved, $error->isPersistent());
        static::assertTrue($error->blockOrder());
    }

    public static function resolvedProvider(): \Generator
    {
        yield 'resolved is a persistent warning' => [true, Error::LEVEL_WARNING];
        yield 'unresolved is a non-persistent error' => [false, Error::LEVEL_ERROR];
    }
}
