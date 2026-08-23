<?php declare(strict_types=1);

namespace Contena\Core\System\Member\Rule;

use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupDefinition;

/**
 * @final
 */
class MemberRequestedGroupRule extends Rule
{
    final public const RULE_NAME = 'memberRequestedGroup';

    /**
     * @param list<string>|null $memberGroupIds
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $memberGroupIds = null
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

        $requestedMemberGroupId = $member->getRequestedGroupId();

        if ($requestedMemberGroupId === null) {
            return RuleComparison::isNegativeOperator($this->operator);
        }

        return RuleComparison::uuids([$requestedMemberGroupId], $this->memberGroupIds, $this->operator);
    }

    public function getConstraints(): array
    {
        $constraints = [
            'operator' => RuleConstraints::uuidOperators(),
        ];

        if ($this->operator === self::OPERATOR_EMPTY) {
            return $constraints;
        }

        $constraints['memberGroupIds'] = RuleConstraints::uuids();

        return $constraints;
    }

    public function getConfig(): RuleConfig
    {
        return new RuleConfig()
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, true)
            ->entitySelectField('memberGroupIds', MemberGroupDefinition::ENTITY_NAME, true);
    }
}
