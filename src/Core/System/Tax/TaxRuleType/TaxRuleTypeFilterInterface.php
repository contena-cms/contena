<?php declare(strict_types=1);

namespace Contena\Core\System\Tax\TaxRuleType;

use Contena\Core\Checkout\Cart\Delivery\Struct\ShippingLocation;
use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;

interface TaxRuleTypeFilterInterface
{
    public function match(TaxRuleEntity $taxRuleEntity, ?UserEntity $user, ShippingLocation $shippingLocation): bool;
}
