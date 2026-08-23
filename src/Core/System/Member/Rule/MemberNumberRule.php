<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Rule;

use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\MemberException;

/**
 * @final
 */
class MemberNumberRule extends Rule
{
    final public const RULE_NAME = 'memberMemberNumber';

    /**
     * @param list<string>|null $numbers
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $numbers = null
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

        if (!\is_array($this->numbers)) {
            throw MemberException::unsupportedValue(\gettype($this->numbers), self::class);
        }

        return RuleComparison::stringArray($member->getMemberNumber(), array_map('strtolower', $this->numbers), $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'numbers' => RuleConstraints::stringArray(),
            'operator' => RuleConstraints::stringOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return new RuleConfig()
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->taggedField('numbers');
    }
}
