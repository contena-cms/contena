<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Hook;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Facade\PriceFacade;
use Contena\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\PriceCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\DataAbstractionLayer\CheapestPrice\CalculatedCheapestPrice;
use Contena\Core\Content\Blog\Hook\Pricing\BlogProxy;
use Contena\Core\Content\Blog\Hook\Pricing\PriceCollectionFacade;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(BlogProxy::class)]
class BlogProxyTest extends TestCase
{
    public function testProxyPropertyAccess(): void
    {
        $blog = new ChannelBlogEntity();

        $blog->setName('foo');
        $blog->setStock(10);

        $blog->setCalculatedPrice(
            new CalculatedPrice(10, 10, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $blog->setCalculatedCheapestPrice(
            new CalculatedCheapestPrice(8, 8, new CalculatedTaxCollection(), new TaxRuleCollection())
        );

        $blog->setCalculatedPrices(new PriceCollection([
            new CalculatedPrice(9, 9, new CalculatedTaxCollection(), new TaxRuleCollection()),
        ]));

        $context = static::createStub(ChannelContext::class);

        $stubs = static::createStub(ScriptPriceStubs::class);

        $proxy = new BlogProxy($blog, $context, $stubs);

        static::assertInstanceOf(PriceFacade::class, $proxy->calculatedPrice, 'Proxy should return a facade for the calculated price');
        static::assertInstanceOf(PriceCollectionFacade::class, $proxy->calculatedPrices, 'Proxy should return a facade for the calculated prices');
        static::assertInstanceOf(PriceFacade::class, $proxy->calculatedCheapestPrice, 'Proxy should return a facade for the calculated cheapest price');
        static::assertSame('foo', $proxy->name, 'Proxy should return the same value as the original object');

        static::assertArrayHasKey('stock', $proxy, 'Proxy should be able to check if a property exists');
    }

    public function testUnsetNotAllowed(): void
    {
        $proxy = new BlogProxy(
            (new ChannelBlogEntity())->assign(['name' => 'foo']),
            static::createStub(ChannelContext::class),
            static::createStub(ScriptPriceStubs::class)
        );

        static::assertSame('foo', $proxy->name, 'Proxy should return the same value as the original object');

        $this->expectExceptionObject(BlogException::proxyManipulationNotAllowed('name'));

        $proxy->offsetUnset('name');
    }

    public function testSetNotAllowed(): void
    {
        $proxy = new BlogProxy(
            (new ChannelBlogEntity())->assign(['name' => 'foo']),
            static::createStub(ChannelContext::class),
            static::createStub(ScriptPriceStubs::class)
        );

        static::assertSame('foo', $proxy->name, 'Proxy should return the same value as the original object');

        $this->expectExceptionObject(BlogException::proxyManipulationNotAllowed('name'));

        $proxy->name = 'bar';
    }
}
