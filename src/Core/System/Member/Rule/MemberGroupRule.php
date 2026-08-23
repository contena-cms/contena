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
class MemberGroupRule extends Rule
{
    final public const RULE_NAME = 'memberMemberGroup';

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

        $groupId = $scope->getChannelContext()->getMember() === null
            ? $scope->getMember()?->getGroupId()
            : null;

        $groupId ??= $scope->getChannelContext()->getMemberGroupId();

        return RuleComparison::uuids([$groupId], $this->memberGroupIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'memberGroupIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return new RuleConfig()
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->entitySelectField('memberGroupIds', MemberGroupDefinition::ENTITY_NAME, true);
    }
}
