<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Rule;

use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\MemberException;
use Symfony\Component\Clock\Clock;

/**
 * @final
 */
class MemberAgeRule extends Rule
{
    final public const RULE_NAME = 'memberAge';

    /**
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?float $age = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof ChannelRuleScope) {
            return false;
        }

        if (!$member = $scope->getMember()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if (!$this->age && $this->operator !== self::OPERATOR_EMPTY) {
            throw MemberException::unsupportedValue(\gettype($this->age), self::class);
        }

        if (!$birthday = $member->getBirthday()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $birthday = new \DateTime('@' . $birthday->getTimestamp());
        $now = Clock::get()->now();

        $age = $now->diff($birthday)->y;

        return RuleComparison::numeric($age, $this->age, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::numericOperators(true),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['age'] = RuleConstraints::float();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return new RuleConfig()
            ->operatorSet(RuleConfig::OPERATOR_SET_NUMBER, true)
            ->intField('age', ['unit' => RuleConfig::UNIT_AGE]);
    }
}
