<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Gateway\Context\Command\Struct;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Framework\Gateway\Context\Command\Struct\ContextGatewayPayloadStruct;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(ContextGatewayPayloadStruct::class)]
class ContextGatewayPayloadStructTest extends TestCase
{
    public function testStructOptional(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateChannelContext();

        $struct = new ContextGatewayPayloadStruct($cart, $context);

        static::assertSame($context, $struct->getChannelContext());
        static::assertSame($cart, $struct->getCart());
        static::assertEquals(new RequestDataBag(), $struct->getData());
    }

    public function testStruct(): void
    {
        $cart = new Cart('hatoken');
        $context = Generator::generateChannelContext();
        $data = new RequestDataBag([
            'foo' => 'bar',
        ]);

        $struct = new ContextGatewayPayloadStruct($cart, $context, $data);

        static::assertSame($context, $struct->getChannelContext());
        static::assertSame($cart, $struct->getCart());
        static::assertSame($data, $struct->getData());
    }
}
