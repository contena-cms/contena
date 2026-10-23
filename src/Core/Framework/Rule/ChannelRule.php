<?php declare(strict_types=1);

namespace Contena\Core\Framework\Rule;

use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelDefinition;

/**
 * @final
 */
class ChannelRule extends Rule
{
    final public const RULE_NAME = 'channel';

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
        return (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->entitySelectField(
                'channelIds',
                ChannelDefinition::ENTITY_NAME,
                true,
                [
                    'criteria' => [
                        'associations' => [
                            'type',
                        ],
                        'filters' => [
                            [
                                'type' => 'not',
                                'operator' => 'AND',
                                'queries' => [
                                    [
                                        'type' => 'equalsAny',
                                        'field' => 'type.id',
                                        'value' => [
                                            Defaults::CHANNEL_TYPE_BLOG_COMPARISON,
                                            Defaults::CHANNEL_TYPE_AGENTIC_COMMERCE,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]
            );
    }
}
