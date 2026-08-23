<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Rule;

use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelRuleScope;

/**
 * @final
 */
class MemberBirthdayRule extends Rule
{
    final public const RULE_NAME = 'memberBirthday';

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
        if (!$scope instanceof ChannelRuleScope) {
            return false;
        }

        if (!$member = $scope->getMember()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        $memberBirthday = $member->getBirthday();

        if ($memberBirthday instanceof \DateTimeImmutable) {
            $memberBirthday = \DateTime::createFromImmutable($memberBirthday);
        }

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $memberBirthday === null;
        }

        if (!$memberBirthday instanceof \DateTime || $this->birthday === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::dateValue(
            $memberBirthday,
            $this->birthday,
            $this->operator
        );
    }

    public function getConfig(): RuleConfig
    {
        return new RuleConfig()
            ->operatorSet(RuleConfig::OPERATOR_SET_DATE, true)
            ->dateField('birthday');
    }
}
