<?php declare(strict_types=1);

namespace Contena\Core\Checkout\Customer\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
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
class CustomerGroupRule extends Rule
{
    final public const RULE_NAME = 'customerCustomerGroup';

    /**
     * @param list<string>|null $customerGroupIds
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $customerGroupIds = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $groupId = $scope->getSalesChannelContext()->getCustomer() === null
            ? $scope->getCustomer()?->getGroupId()
            : null;

        $groupId ??= $scope->getSalesChannelContext()->getCustomerGroupId();

        return RuleComparison::uuids([$groupId], $this->customerGroupIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'customerGroupIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->entitySelectField('customerGroupIds', CustomerGroupDefinition::ENTITY_NAME, true);
    }
}
