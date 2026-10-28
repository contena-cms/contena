<?php declare(strict_types=1);

namespace Contena\Core\Content\Product\SalesChannel\Price;

use Contena\Core\Checkout\Cart\Facade\ScriptPriceStubs;
use Contena\Core\Content\Product\Hook\Pricing\ProductPricingHook;
use Contena\Core\Content\Product\Hook\Pricing\ProductProxy;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Script\Execution\ScriptExecutor;
use Contena\Core\System\SalesChannel\SalesChannelContext;

#[Package('inventory')]
class AppScriptProductPriceCalculator extends AbstractProductPriceCalculator
{
    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractProductPriceCalculator $decorated,
        private readonly ScriptExecutor $scriptExecutor,
        private readonly ScriptPriceStubs $priceStubs
    ) {
    }

    public function getDecorated(): AbstractProductPriceCalculator
    {
        return $this->decorated;
    }

    public function calculate(iterable $products, SalesChannelContext $context): void
    {
        $this->decorated->calculate($products, $context);

        $proxies = [];
        foreach ($products as $product) {
            $proxies[$product->get('id')] = new ProductProxy($product, $context, $this->priceStubs);
        }

        $this->scriptExecutor->execute(new ProductPricingHook($proxies, $context));
    }
}
