<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
#[Package('fundamentals@after-sales')]
class OrderCountRule extends Rule
{
    final public const RULE_NAME = 'customerOrderCount';

    protected string $operator;

    protected int $count;

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$customer = $scope->getCustomer()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::numeric($customer->getOrderCount(), $this->count, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'count' => RuleConstraints::int(),
            'operator' => RuleConstraints::numericOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER)
            ->intField('count');
    }
}
