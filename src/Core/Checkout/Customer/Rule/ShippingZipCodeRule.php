<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Container\ZipCodeRule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleScope;

#[Package('fundamentals@after-sales')]
class ShippingZipCodeRule extends ZipCodeRule
{
    final public const RULE_NAME = 'customerShippingZipCode';

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $address = $scope->getSalesChannelContext()->getShippingLocation()->getAddress()
            ?? $scope->getCustomer()?->getActiveShippingAddress();

        if (!$address) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return $this->matchZipCode($address);
    }
}
