<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Checkout\Cart\Processor\_fixtures;

use Contena\Core\Checkout\Cart\Tax\Struct\TaxRule;
use Contena\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal
 */
class HighTaxes extends TaxRuleCollection
{
    public function __construct()
    {
        parent::__construct([new TaxRule(19)]);
    }
}
