<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Defaults;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\Framework\Rule\ChannelRule;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(ChannelRule::class)]
class ChannelRuleTest extends TestCase
{
    /**
     * @param list<string> $channelIds
     */
    #[DataProvider('provideTestData')]
    public function testMatchesWithCorrectChannel(string $operator, string $currentChannel, ?array $channelIds, bool $expected): void
    {
        $ruleScope = $this->createRuleScope($currentChannel);

        $channelRule = new ChannelRule($operator, $channelIds);

        static::assertSame($expected, $channelRule->match($ruleScope));
    }

    public static function provideTestData(): \Generator
    {
        yield 'matches with correct sales channel' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test')],
            true,
        ];

        yield 'matches with wrong sales channel' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test1')],
            false,
        ];

        yield 'matches with multiple sales channel' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test1'), Uuid::fromStringToHex('test'), Uuid::fromStringToHex('test2')],
            true,
        ];

        yield 'matches not equal with valid sales channel' => [
            Rule::OPERATOR_NEQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test1')],
            true,
        ];

        yield 'matches not equal with invalid sales channel' => [
            Rule::OPERATOR_NEQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test')],
            false,
        ];

        yield 'matches with empty sales channel ids' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            [],
            false,
        ];

        yield 'matches with null channel ids' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            null,
            false,
        ];
    }

    public function testItGetsConfig(): void
    {
        $channelRule = new ChannelRule(Rule::OPERATOR_EQ, []);

        $config = $channelRule->getConfig();

        static::assertSame(
            [
                'operatorSet' => [
                    'operators' => [
                        '=',
                        '!=',
                    ],
                    'isMatchAny' => false,
                ],
                'fields' => [
                    'channelIds' => [
                        'name' => 'channelIds',
                        'type' => 'multi-entity-id-select',
                        'config' => [
                            'entity' => 'channel',
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
                        ],
                    ],
                ],
            ],
            $config->getData()
        );
    }

    public function testProvidesConstraints(): void
    {
        $channelRule = new ChannelRule(Rule::OPERATOR_EQ, []);
        $constraints = $channelRule->getConstraints();

        static::assertArrayHasKey('channelIds', $constraints);
        static::assertEquals(RuleConstraints::uuids(), $constraints['channelIds']);

        static::assertArrayHasKey('operator', $constraints);
        static::assertEquals(RuleConstraints::uuidOperators(false), $constraints['operator']);
    }

    private function createRuleScope(string $channelId): RuleScope
    {
        $channel = new ChannelEntity();
        $channel->setId($channelId);

        $channelContext = Generator::generateChannelContext(
            channel: $channel
        );

        return new CheckoutRuleScope(
            $channelContext
        );
    }
}
