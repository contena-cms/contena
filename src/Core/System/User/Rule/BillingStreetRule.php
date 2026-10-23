<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\User\UserException;

/**
 * @final
 */
class BillingStreetRule extends Rule
{
    final public const RULE_NAME = 'userBillingStreet';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?string $streetName = null
    ) {
        parent::__construct();
    }

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

        if (!\is_string($this->streetName) && $this->operator !== self::OPERATOR_EMPTY) {
            throw UserException::unsupportedValue(\gettype($this->streetName), self::class);
        }

        return RuleComparison::string($address->getStreet(), $this->streetName ?? '', $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::stringOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['streetName'] = RuleConstraints::string();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true)
            ->stringField('streetName');
    }
}
