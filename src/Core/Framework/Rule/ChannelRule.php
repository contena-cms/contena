<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule;

use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelRuleScope;

/**
 * @final
 */
class ChannelRule extends Rule
{
    final public const string RULE_NAME = 'channel';

    /**
     * @param list<string>|null $channelIds
     *
     * @internal
     */
    public function __construct(
        protected string $operator = self::OPERATOR_EQ,
        protected ?array $channelIds = null
    ) {
        parent::__construct();
    }

    public function match(RuleScope $scope): bool
    {
        if (!$scope instanceof ChannelRuleScope) {
            return false;
        }

        return RuleComparison::uuids([$scope->getChannelContext()->getChannelId()], $this->channelIds, $this->operator);
    }

    public function getConstraints(): array
    {
        return [
            'channelIds' => RuleConstraints::uuids(),
            'operator' => RuleConstraints::uuidOperators(false),
        ];
    }

    public function getConfig(): RuleConfig
    {
        return new RuleConfig()
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->entitySelectField('channelIds', ChannelDefinition::ENTITY_NAME, true);
    }
}
