<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Rule\Container\ZipCodeRule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleScope;

class BillingZipCodeRule extends ZipCodeRule
{
    final public const RULE_NAME = 'userBillingZipCode';

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$user = $scope->getUser()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!$address = $user->getActiveBillingAddress()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return $this->matchZipCode($address);
    }
}
