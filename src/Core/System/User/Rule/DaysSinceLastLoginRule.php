<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Framework\Rule\Container\DaysSinceRule;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
class DaysSinceLastLoginRule extends DaysSinceRule
{
    final public const string RULE_NAME = 'userDaysSinceLastLogin';

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        return $scope instanceof UserRuleScope ? $scope->getUser()->getLastLogin() : null;
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return $scope instanceof UserRuleScope;
    }
}
