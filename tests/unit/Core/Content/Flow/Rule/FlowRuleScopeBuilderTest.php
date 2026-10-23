<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\CartDataCollectorInterface;
use Contena\Core\Checkout\Cart\Delivery\DeliveryBuilder;
use Contena\Core\Checkout\Cart\Delivery\Struct\DeliveryCollection;
use Contena\Core\Checkout\Cart\Order\OrderConverter;
use Contena\Core\Checkout\Order\OrderEntity;
use Contena\Core\Content\Flow\Rule\FlowRuleScope;
use Contena\Core\Content\Flow\Rule\FlowRuleScopeBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(FlowRuleScopeBuilder::class)]
class FlowRuleScopeBuilderTest extends TestCase
{
    private Stub&OrderConverter $orderConverter;

    private Stub&DeliveryBuilder $deliveryBuilder;

    private MockObject&CartDataCollectorInterface $cartDataCollector;

    private FlowRuleScopeBuilder $scopeBuilder;

    protected function setUp(): void
    {
        $this->orderConverter = static::createStub(OrderConverter::class);
        $this->deliveryBuilder = static::createStub(DeliveryBuilder::class);
        $this->cartDataCollector = $this->createMock(CartDataCollectorInterface::class);
        $this->scopeBuilder = new FlowRuleScopeBuilder($this->orderConverter, $this->deliveryBuilder, [$this->cartDataCollector]);
    }

    public function testBuild(): void
    {
        $mockContext = static::createStub(ChannelContext::class);
        $cart = new Cart('test');
        $this->orderConverter->method('assembleChannelContext')->willReturn($mockContext);
        $this->orderConverter->method('convertToCart')->willReturn($cart);
        $this->deliveryBuilder->method('build')->willReturn(new DeliveryCollection());
        $this->cartDataCollector->expects($this->exactly(2))->method('collect');

        $order = new OrderEntity();
        $order->setId(Uuid::randomHex());
        $expected = new FlowRuleScope($order, $cart, $mockContext);
        $context = Context::createDefaultContext();

        static::assertEquals($expected, $this->scopeBuilder->build($order, $context));
        static::assertEquals($expected, $this->scopeBuilder->build($order, $context));

        $this->scopeBuilder->reset();

        static::assertEquals($expected, $this->scopeBuilder->build($order, $context));
    }
}
