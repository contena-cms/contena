<?php declare(strict_types=1);

namespace Contena\Core\System\Tax\TaxRuleType;

use Contena\Core\System\User\UserEntity;
use Contena\Core\Framework\Log\Package;

interface TaxRuleTypeFilterInterface
{
    public function match(TaxRuleEntity $taxRuleEntity, ?UserEntity $user, ShippingLocation $shippingLocation): bool;
}
