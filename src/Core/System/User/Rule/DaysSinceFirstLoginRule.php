<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Framework\Rule\Container\DaysSinceRule;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
class DaysSinceFirstLoginRule extends DaysSinceRule
{
    final public const string RULE_NAME = 'userDaysSinceFirstLogin';

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        return $scope instanceof UserRuleScope ? $scope->getUser()->getFirstLogin() : null;
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return $scope instanceof UserRuleScope;
    }
}
