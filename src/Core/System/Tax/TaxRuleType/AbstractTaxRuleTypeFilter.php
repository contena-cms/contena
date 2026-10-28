<?php

declare(strict_types=1);

namespace Contena\Core\System\Tax\TaxRuleType;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Tax\Aggregate\TaxRule\TaxRuleEntity;
use Symfony\Component\Clock\Clock;

#[Package('checkout')]
abstract class AbstractTaxRuleTypeFilter implements TaxRuleTypeFilterInterface
{
    protected function isTaxActive(TaxRuleEntity $taxRuleEntity): bool
    {
        return $taxRuleEntity->getActiveFrom() < Clock::get()->now()->setTimezone(new \DateTimeZone('UTC'));
    }
}
