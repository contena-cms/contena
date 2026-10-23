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
class AffiliateCodeRule extends Rule
{
    final public const RULE_NAME = 'userAffiliateCode';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?string $affiliateCode = null
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

        if (!$this->affiliateCode && $this->operator !== self::OPERATOR_EMPTY) {
            throw UserException::unsupportedValue(\gettype($this->affiliateCode), self::class);
        }

        if (!$affiliateCode = $user->getAffiliateCode()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::string($affiliateCode, $this->affiliateCode ?? '', $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::stringOperators(true),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['affiliateCode'] = RuleConstraints::string();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true)
            ->stringField('affiliateCode');
    }
}
