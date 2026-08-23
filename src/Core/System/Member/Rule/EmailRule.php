<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Rule;

use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberException;

/**
 * @final
 */
class EmailRule extends Rule
{
    final public const RULE_NAME = 'memberEmail';

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
        if (!$scope instanceof ChannelRuleScope) {
            return false;
        }

        if (!$member = $scope->getMember()) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        if ($this->email && mb_strpos($this->email, '*') !== false) {
            return $this->matchPartially($member);
        }

        return $this->matchExact($member);
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
        return new RuleConfig()
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->stringField('email');
    }

    private function matchPartially(MemberEntity $member): bool
    {
        if ($this->email === null) {
            throw MemberException::unsupportedValue(\gettype($this->email), self::class);
        }

        $email = str_replace('\*', '(.*?)', preg_quote($this->email, '/'));
        $regex = \sprintf('/^%s$/i', $email);

        return match ($this->operator) {
            Rule::OPERATOR_EQ => preg_match($regex, $member->getEmail()) === 1,
            Rule::OPERATOR_NEQ => preg_match($regex, $member->getEmail()) !== 1,
            default => throw MemberException::unsupportedOperator($this->operator, self::class),
        };
    }

    private function matchExact(MemberEntity $member): bool
    {
        if ($this->email === null) {
            throw MemberException::unsupportedValue(\gettype($this->email), self::class);
        }

        return RuleComparison::string($member->getEmail(), $this->email, $this->operator);
    }
}
