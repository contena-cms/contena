<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Rule;

use Contena\Core\Framework\Rule\Container\DaysSinceRule;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelRuleScope;

/**
 * @final
 */
class DaysSinceLastLoginRule extends DaysSinceRule
{
    final public const string RULE_NAME = 'memberDaysSinceLastLogin';

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        return $scope instanceof ChannelRuleScope ? $scope->getMember()?->getLastLogin() : null;
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return $scope instanceof ChannelRuleScope;
    }
}
