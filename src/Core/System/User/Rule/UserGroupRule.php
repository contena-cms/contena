<?php declare(strict_types=1);

namespace Contena\Core\System\User\Rule;

use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleComparison;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;

/**
 * @final
 */
class UserGroupRule extends Rule
{
    final public const RULE_NAME = 'userUserGroup';

    /**
     * @param list<string>|null $userGroupIds
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $userGroupIds = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof CheckoutRuleScope) {
            return false;
        }

        $groupId = $scope->getChannelContext()->getUser() === null
            ? $scope->getUser()?->getGroupId()
            : null;

        $groupId ??= $scope->getChannelContext()->getUserGroupId();

        return RuleComparison::uuids([$groupId], $this->userGroupIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'userGroupIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->entitySelectField('userGroupIds', UserGroupDefinition::ENTITY_NAME, true);
    }
}
