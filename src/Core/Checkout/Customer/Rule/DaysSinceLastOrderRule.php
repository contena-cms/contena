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
class DaysSinceLastOrderRule extends DaysSinceRule
{
    final public const RULE_NAME = 'customerDaysSinceLastOrder';

    public int $count;

    protected function getDate(RuleScope $scope): ?\DateTimeInterface
    {
        $customer = $scope instanceof CheckoutRuleScope
            ? $scope->getCustomer()
            : $scope->getSalesChannelContext()->getCustomer();

        return $customer?->getLastOrderDate();
    }

    protected function supportsScope(RuleScope $scope): bool
    {
        return $scope instanceof CheckoutRuleScope;
    }
}
