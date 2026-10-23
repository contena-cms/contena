<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Error\Error;
use Contena\Core\Content\Blog\Cart\MinOrderQuantityError;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(MinOrderQuantityError::class)]
class MinOrderQuantityErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $id = Uuid::randomHex();
        $error = new MinOrderQuantityError(id: $id, name: 'Shirt', quantity: 5);

        static::assertSame(
            'The quantity of blog Shirt did not meet the minimum order quantity threshold. The quantity has automatically been increased to 5',
            $error->getMessage()
        );
        static::assertSame('min-order-quantity' . $id, $error->getId());
        static::assertSame('min-order-quantity', $error->getMessageKey());
        static::assertSame(Error::LEVEL_WARNING, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertTrue($error->isPersistent());
        static::assertSame(['name' => 'Shirt', 'quantity' => 5], $error->getParameters());
    }
}
