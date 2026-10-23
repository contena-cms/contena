<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Contract;

use Contena\Core\Framework\Log\Package;

interface RuleIdAware
{
    public function getAvailabilityRuleId(): ?string;
}
