<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Rule\Container\DaysSinceRule;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
class DaysSinceFirstLoginRule extends DaysSinceRule
{
    final public const RULE_NAME = 'userDaysSinceFirstLogin';

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        $user = $scope instanceof CheckoutRuleScope
            ? $scope->getUser()
            : $scope->getChannelContext()->getUser();

        return $user?->getFirstLogin();
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return $scope instanceof CheckoutRuleScope;
    }
}
