<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Contena\Core\System\Country\Aggregate\CountryState\CountryStateDefinition;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @final
 */
class BillingStateRule extends Rule
{
    final public const RULE_NAME = 'userBillingState';

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

        if (!$user = $scope->getUser()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!$address = $user->getActiveBillingAddress()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $stateId = $address->getCountryStateId();
        $parameter = [$stateId];
        if ($stateId === '') {
            $parameter = [];
        }

        return RuleComparison::uuids($parameter, $this->stateIds, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => [
                new NotBlank(),
            ],
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['stateIds'] = [new NotBlank(), new ArrayOfUuid()];

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true)
            ->entitySelectField('stateIds', CountryStateDefinition::ENTITY_NAME, true);
    }
}
