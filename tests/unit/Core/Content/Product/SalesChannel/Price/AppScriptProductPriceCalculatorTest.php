<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Product\SalesChannel\Price;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Contena\Core\Content\Product\SalesChannel\Price\AppScriptProductPriceCalculator;
use Contena\Core\Content\Product\SalesChannel\Price\ProductPriceCalculator;
use Contena\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppScriptProductPriceCalculator::class)]
class AppScriptProductPriceCalculatorTest extends TestCase
{
    public function testHookWillBeExecuted(): void
    {
        $product1 = new SalesChannelProductEntity();
        $product1->setId('product-1');
        $product2 = new SalesChannelProductEntity();
        $product2->setId('product-2');

        $products = [
            $product1,
            $product2,
        ];

        $executor = $this->createMock(ScriptExecutor::class);
        $executor->expects($this->once())->method('execute');

        $decorated = $this->createMock(ProductPriceCalculator::class);
        $decorated->expects($this->once())->method('calculate')->with($products);

        $calculator = new AppScriptProductPriceCalculator($decorated, $executor, static::createStub(ScriptPriceStubs::class));

        $calculator->calculate($products, static::createStub(SalesChannelContext::class));
    }
}
