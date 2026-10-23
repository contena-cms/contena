<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Events;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartBehavior;
use Contena\Core\Checkout\Cart\LineItem\CartDataCollection;
use Contena\Core\Content\Blog\Events\BlogCartDataContextHashEvent;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(BlogCartDataContextHashEvent::class)]
class BlogCartDataContextHashEventTest extends TestCase
{
    public function testExposesTheContext(): void
    {
        $channelContext = Generator::generateChannelContext();

        $data = new CartDataCollection();
        $cart = new Cart('test');
        $behavior = new CartBehavior();

        $event = new BlogCartDataContextHashEvent($data, $cart, $channelContext, $behavior);

        static::assertSame($data, $event->getData());
        static::assertSame($cart, $event->getOriginalCart());
        static::assertSame($behavior, $event->getBehavior());
        static::assertSame($channelContext, $event->getChannelContext());
        static::assertSame($channelContext->getContext(), $event->getContext());
        static::assertSame([], $event->getParts());
    }

    public function testPartsAreSortedByNameAndOverwritten(): void
    {
        $event = new BlogCartDataContextHashEvent(new CartDataCollection(), new Cart('test'), Generator::generateChannelContext(), new CartBehavior());

        $event->addPart('b', 'second');
        $event->addPart('a', ['first']);
        $event->addPart('b', 2);

        static::assertSame(['a' => ['first'], 'b' => 2], $event->getParts());
    }
}
