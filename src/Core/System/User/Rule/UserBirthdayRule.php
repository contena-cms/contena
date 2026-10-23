<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

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
class UserBirthdayRule extends Rule
{
    final public const RULE_NAME = 'userBirthday';

    /**
     * @internal
     *
     * @param string|array{from: string, to: string}|null $birthday
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected string|array|null $birthday = null
    ) {
        parent::__construct();
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::dateOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['birthday'] = RuleConstraints::date();

        if ($this->operator === self::OPERATOR_BETWEEN) {
            $constraints['birthday'] = RuleConstraints::dateBetween();
        }

        return $constraints;
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        if (!$user = $scope->getUser()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $userBirthday = $user->getBirthday();

        if ($userBirthday instanceof \DateTimeImmutable) {
            $userBirthday = \DateTime::createFromImmutable($userBirthday);
        }

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $userBirthday === null;
        }

        if (!$userBirthday instanceof \DateTime || $this->birthday === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::dateValue(
            $userBirthday,
            $this->birthday,
            $this->operator
        );
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_DATE, true)
            ->dateField('birthday');
    }
}
