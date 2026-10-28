<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\LineItem\CartDataCollection;
use Contena\Core\Content\Product\Events\ProductCartDataContextHashEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductCartDataContextHashEvent::class)]
class ProductCartDataContextHashEventTest extends TestCase
{
    public function testExposesTheContext(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $data = new CartDataCollection();
        $cart = new Cart('test');
        $behavior = new CartBehavior();

        $event = new ProductCartDataContextHashEvent($data, $cart, $salesChannelContext, $behavior);

        static::assertSame($data, $event->getData());
        static::assertSame($cart, $event->getOriginalCart());
        static::assertSame($behavior, $event->getBehavior());
        static::assertSame($salesChannelContext, $event->getSalesChannelContext());
        static::assertSame($salesChannelContext->getContext(), $event->getContext());
        static::assertSame([], $event->getParts());
    }

    public function testPartsAreSortedByNameAndOverwritten(): void
    {
        $event = new ProductCartDataContextHashEvent(new CartDataCollection(), new Cart('test'), Generator::generateSalesChannelContext(), new CartBehavior());

        $event->addPart('b', 'second');
        $event->addPart('a', ['first']);
        $event->addPart('b', 2);

        static::assertSame(['a' => ['first'], 'b' => 2], $event->getParts());
    }
}
