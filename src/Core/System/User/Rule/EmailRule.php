<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
class EmailRule extends Rule
{
    final public const RULE_NAME = 'userEmail';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?string $email = null
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

        if ($this->email && mb_strpos($this->email, '*') !== false) {
            return $this->matchPartially($user);
        }

        return $this->matchExact($user);
    }

    public function getConstraints(): array
    {
        return [
            'operator' => RuleConstraints::stringOperators(false),
            'email' => RuleConstraints::string(),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->stringField('email');
    }

    private function matchPartially(UserEntity $user): bool
    {
        if ($this->email === null) {
            throw UserException::unsupportedValue(\gettype($this->email), self::class);
        }

        $email = str_replace('\*', '(.*?)', preg_quote($this->email, '/'));
        $regex = \sprintf('/^%s$/Di', $email);

        return match ($this->operator) {
            Rule::OPERATOR_EQ => preg_match($regex, $user->getEmail()) === 1,
            Rule::OPERATOR_NEQ => preg_match($regex, $user->getEmail()) !== 1,
            default => throw UserException::unsupportedOperator($this->operator, self::class),
        };
    }

    private function matchExact(UserEntity $user): bool
    {
        if ($this->email === null) {
            throw UserException::unsupportedValue(\gettype($this->email), self::class);
        }

        return RuleComparison::string($user->getEmail(), $this->email, $this->operator);
    }
}
