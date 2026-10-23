<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;

/**
 * @final
 */
class ShippingStateRule extends Rule
{
    final public const RULE_NAME = 'userShippingState';

    /**
     * @param list<string>|null $stateIds
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $stateIds = null
    ) {
        parent::__construct();
    }

    /**
     * @throws UnsupportedOperatorException
     */
    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $state = $scope->getChannelContext()->getShippingLocation()->getState()
            ?? $scope->getUser()?->getActiveShippingAddress()?->getCountryState();

        if (!$state) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $stateId = $state->getId();
        $parameter = [$stateId];
        if ($stateId === '') {
            $parameter = [];
        }

        return RuleComparison::uuids($parameter, $this->stateIds, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::stringOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['stateIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true)
            ->entitySelectField('stateIds', CountryStateDefinition::ENTITY_NAME, true);
    }
}
