<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Rule\ChannelRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelRuleScope;
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
        $ruleScope = new ChannelRuleScope($this->createChannelContext($currentChannel));

        $channelRule = new ChannelRule($operator, $channelIds);

        static::assertSame($expected, $channelRule->match($ruleScope));
    }

    public static function provideTestData(): \Generator
    {
        yield 'matches with correct channel' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test')],
            true,
        ];

        yield 'matches with wrong channel' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test1')],
            false,
        ];

        yield 'matches with multiple channels' => [
            Rule::OPERATOR_EQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test1'), Uuid::fromStringToHex('test'), Uuid::fromStringToHex('test2')],
            true,
        ];

        yield 'matches not equal with valid channel' => [
            Rule::OPERATOR_NEQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test1')],
            true,
        ];

        yield 'matches not equal with invalid channel' => [
            Rule::OPERATOR_NEQ,
            Uuid::fromStringToHex('test'),
            [Uuid::fromStringToHex('test')],
            false,
        ];

        yield 'matches with empty channel ids' => [
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
                        ],
                    ],
                ],
            ],
            $channelRule->getConfig()->getData()
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

    private function createChannelContext(string $channelId): ChannelContext
    {
        $channel = Generator::generateChannelContext()->getChannel();
        $channel->setId($channelId);

        return Generator::generateChannelContext(channel: $channel);
    }
}
