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
class ShippingCityRule extends Rule
{
    final public const RULE_NAME = 'userShippingCity';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?string $cityName = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $address = $scope->getChannelContext()->getShippingLocation()->getAddress()
            ?? $scope->getUser()?->getActiveShippingAddress();

        if (!$address) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!\is_string($this->cityName) && $this->operator !== self::OPERATOR_EMPTY) {
            throw UserException::unsupportedValue(\gettype($this->cityName), self::class);
        }

        return RuleComparison::string($address->getCity(), $this->cityName ?? '', $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::stringOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['cityName'] = RuleConstraints::string();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true)
            ->stringField('cityName');
    }
}
