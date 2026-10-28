<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Hook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Facade\PriceFacade;
use Contena\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Contena\Core\Content\Product\Hook\Pricing\PriceCollectionFacade;
use Contena\Core\Content\Product\Hook\Pricing\ProductProxy;
use Contena\Core\Content\Product\ProductException;
use Contena\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductProxy::class)]
class ProductProxyTest extends TestCase
{
    public function testProxyPropertyAccess(): void
    {
        $product = new SalesChannelProductEntity();

        $product->setName('foo');
        $product->setStock(10);

        $product->setCalculatedPrice(
            new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $product->setCalculatedCheapestPrice(
            new CalculatedCheapestPrice(8, 8, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $product->setCalculatedPrices(new PriceCollection([
        ]));

        $context = static::createStub(SalesChannelContext::class);

        $stubs = static::createStub(ScriptPriceStubs::class);

        $proxy = new ProductProxy($product, $context, $stubs);

        static::assertInstanceOf(PriceFacade::class, $proxy->calculatedPrice, 'Proxy should return a facade for the calculated price');
        static::assertInstanceOf(PriceCollectionFacade::class, $proxy->calculatedPrices, 'Proxy should return a facade for the calculated prices');
        static::assertInstanceOf(PriceFacade::class, $proxy->calculatedCheapestPrice, 'Proxy should return a facade for the calculated cheapest price');
        static::assertSame('foo', $proxy->name, 'Proxy should return the same value as the original object');

        static::assertArrayHasKey('stock', $proxy, 'Proxy should be able to check if a property exists');
    }

    public function testUnsetNotAllowed(): void
    {
        $proxy = new ProductProxy(
            (new SalesChannelProductEntity())->assign(['name' => 'foo']),
            static::createStub(SalesChannelContext::class),
            static::createStub(ScriptPriceStubs::class)
        );

        static::assertSame('foo', $proxy->name, 'Proxy should return the same value as the original object');

        $this->expectExceptionObject(ProductException::proxyManipulationNotAllowed('name'));

        $proxy->offsetUnset('name');
    }

    public function testSetNotAllowed(): void
    {
        $proxy = new ProductProxy(
            (new SalesChannelProductEntity())->assign(['name' => 'foo']),
            static::createStub(SalesChannelContext::class),
            static::createStub(ScriptPriceStubs::class)
        );

        static::assertSame('foo', $proxy->name, 'Proxy should return the same value as the original object');

        $this->expectExceptionObject(ProductException::proxyManipulationNotAllowed('name'));

        $proxy->name = 'bar';
    }
}
