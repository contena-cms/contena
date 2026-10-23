<?php declare(strict_types=1);

namespace Contena\Core\Test\Stub\Rule;

use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleScope;

class FalseRule extends Rule
{
    final public const RULE_NAME = 'false';

    public function match(RuleScope $matchContext): bool
    {
        return false;
    }

    public function getConstraints(): array
    {
        return [];
    }
}
