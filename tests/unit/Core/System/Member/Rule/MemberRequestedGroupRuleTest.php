<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupDefinition;
use Contena\Core\System\Member\Aggregate\MemberGroup\MemberGroupEntity;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Rule\MemberRequestedGroupRule;

/**
 * @internal
 */
#[CoversClass(MemberRequestedGroupRule::class)]
#[Group('rules')]
class MemberRequestedGroupRuleTest extends TestCase
{
    private MemberRequestedGroupRule $rule;

    protected function setUp(): void
    {
        $this->rule = new MemberRequestedGroupRule();
    }

    public function testName(): void
    {
        static::assertSame('memberRequestedGroup', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $constraints, 'Constraint operator not found in Rule');
        static::assertArrayHasKey('memberGroupIds', $constraints, 'Constraint memberGroupIds not found in Rule');
        static::assertEquals(RuleConstraints::uuidOperators(), $constraints['operator']);
        static::assertEquals(RuleConstraints::uuids(), $constraints['memberGroupIds']);
    }

    public function testGetConstraintsOperatorEmpty(): void
    {
        $rule = new MemberRequestedGroupRule(Rule::OPERATOR_EMPTY);
        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('operator', $constraints, 'Constraint operator not found in Rule');
        static::assertArrayNotHasKey('memberGroupIds', $constraints, 'Constraint memberGroupIds found in Rule');
        static::assertEquals(RuleConstraints::uuidOperators(), $constraints['operator']);
    }

    public function testGetConfig(): void
    {
        $config = $this->rule->getConfig();
        static::assertSame([
            'operatorSet' => [
                'operators' => [
                    ...RuleConfig::OPERATOR_SET_STRING,
                    Rule::OPERATOR_EMPTY,
                ],
                'isMatchAny' => false,
            ],
            'fields' => [
                'memberGroupIds' => [
                    'name' => 'memberGroupIds',
                    'type' => 'multi-entity-id-select',
                    'config' => [
                        'entity' => MemberGroupDefinition::ENTITY_NAME,
                    ],
                ],
            ],
        ], $config->getData());
    }

    /**
     * @param array<string> $memberGroupIds
     */
    #[DataProvider('getMatchValues')]
    public function testMemberRequestedGroupRuleMatching(bool $expected, bool $loggedIn, ?string $requestedGroupId, array $memberGroupIds, string $operator): void
    {
        $member = null;

        if ($loggedIn) {
            $member = new MemberEntity();

            if ($requestedGroupId !== null) {
                $memberGroup = new MemberGroupEntity();
                $memberGroup->setId($requestedGroupId);
                $member->setRequestedGroup($memberGroup);
                $member->setRequestedGroupId($requestedGroupId);
            }
        }

        $context = static::createStub(ChannelContext::class);
        $context->method('getMember')->willReturn($member);
        $scope = new ChannelRuleScope($context);

        $this->rule->assign(['memberGroupIds' => $memberGroupIds, 'operator' => $operator]);

        static::assertSame($expected, $this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = static::createStub(RuleScope::class);
        $this->rule->assign(['memberGroupIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return \Traversable<string, array{expected: bool, loggedIn: bool, requestedGroupId: string|null, memberGroupIds: array<string>, operator: string}>
     */
    public static function getMatchValues(): \Traversable
    {
        $id = Uuid::randomHex();

        yield 'operator_one_of / no match' => [
            'expected' => false,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'memberGroupIds' => [Uuid::randomHex()],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'operator_one_of / one match' => [
            'expected' => true,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'memberGroupIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'operator_one_of / empty' => [
            'expected' => false,
            'loggedIn' => true,
            'requestedGroupId' => null,
            'memberGroupIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'operator_one_of / not logged in' => [
            'expected' => false,
            'loggedIn' => false,
            'requestedGroupId' => null,
            'memberGroupIds' => [$id],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'operator_none_of / no match' => [
            'expected' => true,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'memberGroupIds' => [Uuid::randomHex()],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'operator_none_of / one match' => [
            'expected' => false,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'memberGroupIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'operator_none_of / empty' => [
            'expected' => true,
            'loggedIn' => true,
            'requestedGroupId' => null,
            'memberGroupIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'operator_none_of / not logged in' => [
            'expected' => true,
            'loggedIn' => false,
            'requestedGroupId' => null,
            'memberGroupIds' => [$id],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'operator_empty / empty' => [
            'expected' => true,
            'loggedIn' => true,
            'requestedGroupId' => null,
            'memberGroupIds' => [],
            'operator' => Rule::OPERATOR_EMPTY,
        ];

        yield 'operator_empty / not empty' => [
            'expected' => false,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'memberGroupIds' => [],
            'operator' => Rule::OPERATOR_EMPTY,
        ];

        yield 'operator_empty / not logged in' => [
            'expected' => true,
            'loggedIn' => false,
            'requestedGroupId' => null,
            'memberGroupIds' => [],
            'operator' => Rule::OPERATOR_EMPTY,
        ];
    }
}
