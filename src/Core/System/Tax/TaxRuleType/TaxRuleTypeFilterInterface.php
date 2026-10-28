<?php declare(strict_types=1);

namespace Contena\Core\System\Tax\TaxRuleType;

use Contena\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Contena\Core\Checkout\Customer\CustomerEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;

#[Package('checkout')]
interface TaxRuleTypeFilterInterface
{
    public function match(TaxRuleEntity $taxRuleEntity, ?CustomerEntity $customer, ShippingLocation $shippingLocation): bool;
}
