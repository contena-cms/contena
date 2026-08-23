<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Contract;

interface RuleIdAware
{
    public function getAvailabilityRuleId(): ?string;
}
