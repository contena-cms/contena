<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Container\DaysSinceRule;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class DaysSinceLastLoginRule extends DaysSinceRule
{
    final public const RULE_NAME = 'customerDaysSinceLastLogin';

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        $customer = $scope instanceof CheckoutRuleScope
            ? $scope->getCustomer()
            : $scope->getSalesChannelContext()->getCustomer();

        return $customer?->getLastLogin();
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return $scope instanceof CheckoutRuleScope;
    }
}
