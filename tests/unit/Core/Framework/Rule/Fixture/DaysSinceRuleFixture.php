<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Rule\Fixture;

use Contena\Core\Framework\Rule\Container\DaysSinceRule;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @internal
 */
class DaysSinceRuleFixture extends DaysSinceRule
{
    final public const RULE_NAME = 'fixtureDaysSince';

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        return null;
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return false;
    }
}
