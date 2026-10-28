<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\Checkout;

use Contena\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Contena\Core\Checkout\Cart\Price\Struct\ListPrice;
use Contena\Core\Checkout\Cart\Price\Struct\ReferencePrice;
use Contena\Core\Checkout\Cart\Price\Struct\RegulationPrice;
use Contena\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;

class EmptyPrice extends CalculatedPrice
{
    public function __construct(
        float $unitPrice = 0,
        float $totalPrice = 0,
        ?CalculatedTaxCollection $calculatedTaxes = null,
        ?TaxRuleCollection $taxRules = null,
        int $quantity = 1,
        ?ReferencePrice $referencePrice = null,
        ?ListPrice $listPrice = null,
        ?RegulationPrice $regulationPrice = null
    ) {
        $calculatedTaxes ??= new CalculatedTaxCollection();
        $taxRules ??= new TaxRuleCollection();

        parent::__construct($unitPrice, $totalPrice, $calculatedTaxes, $taxRules, $quantity, $referencePrice, $listPrice, $regulationPrice);
    }

    public function getApiAlias(): string
    {
        return 'empty_price';
    }
}
