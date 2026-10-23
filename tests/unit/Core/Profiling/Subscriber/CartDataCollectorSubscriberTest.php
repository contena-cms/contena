<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Profiling\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\AbstractCartPersister;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\LineItem\LineItemCollection;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\CartPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Routing\Event\ChannelContextResolvedEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Profiling\Subscriber\CartDataCollectorSubscriber;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Currency\CurrencyEntity;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CartDataCollectorSubscriber::class)]
class CartDataCollectorSubscriberTest extends TestCase
{
    public function testEvents(): void
    {
        static::assertSame(
            [
                ChannelContextResolvedEvent::class => 'onContextResolved',
            ],
            CartDataCollectorSubscriber::getSubscribedEvents()
        );
    }

    public function testDataCollection(): void
    {
        $cartToken = Uuid::randomHex();

        $cart = new Cart('test-cart');
        $lineItem = new LineItem('line-item-id', 'blog', 'blog-id', 2);
        $lineItem->setLabel('Test Blog');
        $lineItem->setPrice(new CalculatedPrice(
            100.00,
            200.00,
            new CalculatedTaxCollection(),
            new TaxRuleCollection()
        ));

        $cart->addLineItems(new LineItemCollection([$lineItem]));
        $cart->setPrice(new CartPrice(
            200.00,
            200.00,
            200.00,
            new CalculatedTaxCollection(),
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        ));

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('EUR');

        $channelContext = static::createStub(ChannelContext::class);
        $context = new Context(new SystemSource());
        $channelContext->method('getContext')->willReturn($context);
        $channelContext->method('getCurrency')->willReturn($currency);

        $event = new ChannelContextResolvedEvent($channelContext, $cartToken);

        $cartPersister = static::createStub(AbstractCartPersister::class);
        $cartPersister->method('load')->willReturn($cart);

        $subscriber = new CartDataCollectorSubscriber($cartPersister);
        $subscriber->onContextResolved($event);
        $subscriber->collect(new Request(), new Response());

        static::assertEquals($cart, $subscriber->getCart());
        static::assertSame(1, $subscriber->getItemCount());
        static::assertSame(200.00, $subscriber->getCartTotal());
        static::assertSame('EUR', $subscriber->getCurrency());
    }

    public function testEmptyCart(): void
    {
        $cartToken = Uuid::randomHex();

        $cart = new Cart('empty-cart');

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('EUR');

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getCurrency')->willReturn($currency);

        $event = new ChannelContextResolvedEvent($channelContext, $cartToken);
        $cartPersister = static::createStub(AbstractCartPersister::class);
        $cartPersister->method('load')->willReturn($cart);

        $subscriber = new CartDataCollectorSubscriber($cartPersister);
        $subscriber->onContextResolved($event);
        $subscriber->collect(new Request(), new Response());
        static::assertEquals($cart, $subscriber->getCart());
        static::assertSame(0, $subscriber->getItemCount());
        static::assertSame(0.0, $subscriber->getCartTotal());
        static::assertSame('EUR', $subscriber->getCurrency());
    }

    public function testReset(): void
    {
        $cartToken = Uuid::randomHex();

        $cart = new Cart('test-cart');

        $currency = new CurrencyEntity();
        $currency->setId(Uuid::randomHex());
        $currency->setIsoCode('EUR');

        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getCurrency')->willReturn($currency);

        $event = new ChannelContextResolvedEvent($channelContext, $cartToken);

        $cartPersister = static::createStub(AbstractCartPersister::class);
        $cartPersister->method('load')->willReturn($cart);

        $subscriber = new CartDataCollectorSubscriber($cartPersister);
        $subscriber->onContextResolved($event);
        $subscriber->collect(new Request(), new Response());

        static::assertEquals($cart, $subscriber->getCart());

        $subscriber->reset();

        $subscriber->collect(new Request(), new Response());

        static::assertNull($subscriber->getCart());
    }
}
