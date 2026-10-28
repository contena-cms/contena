<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\Hook\Pricing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Contena\Core\Content\Product\Hook\Pricing\ProductPricingHook;
use Contena\Core\Content\Product\Hook\Pricing\ProductProxy;
use Contena\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\SalesChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Contena\Core\System\SystemConfig\Facade\SystemConfigFacadeHookFactory;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductPricingHook::class)]
class ProductPricingHookTest extends TestCase
{
    public function testGetProducts(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);

        $productProxy = new ProductProxy(
            (new SalesChannelProductEntity())->assign(['name' => 'foo']),
            $salesChannelContext,
            static::createStub(ScriptPriceStubs::class)
        );
        $productPricingHook = new ProductPricingHook([$productProxy], $salesChannelContext);

        static::assertSame([$productProxy], $productPricingHook->getProducts());
    }

    public function testGetServiceIds(): void
    {
        static::assertSame(
            [
                RepositoryFacadeHookFactory::class,
                SystemConfigFacadeHookFactory::class,
                SalesChannelRepositoryFacadeHookFactory::class,
            ],
            ProductPricingHook::getServiceIds()
        );
    }

    public function testGetName(): void
    {
        $productPricingHook = new ProductPricingHook([], static::createStub(SalesChannelContext::class));

        static::assertSame('product-pricing', $productPricingHook->getName());
    }

    public function testGetSalesChannelContext(): void
    {
        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $productPricingHook = new ProductPricingHook([], $salesChannelContext);

        static::assertSame($salesChannelContext, $productPricingHook->getSalesChannelContext());
    }
}
