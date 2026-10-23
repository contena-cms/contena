<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures;

use Contena\Core\Checkout\Cart\Price\CashRounding;
use Contena\Core\Checkout\Cart\Price\GrossPriceCalculator;
use Contena\Core\Checkout\Cart\Price\NetPriceCalculator;
use Contena\Core\Checkout\Cart\Price\QuantityPriceCalculator;
use Contena\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Checkout\Cart\Tax\TaxCalculator;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
class CalculatedItem extends QuantityItem
{
    public function __construct(
        float $price,
        TaxRuleCollection $taxes,
        ChannelContext $context,
        bool $good = true,
        int $quantity = 1
    ) {
        parent::__construct($price, $taxes, $good, $quantity);

        $calculator = new QuantityPriceCalculator(
            new GrossPriceCalculator(new TaxCalculator(), new CashRounding()),
            new NetPriceCalculator(new TaxCalculator(), new CashRounding())
        );

        \assert($this->getPriceDefinition() instanceof QuantityPriceDefinition);
        $this->price = $calculator->calculate($this->getPriceDefinition(), $context);
    }
}
