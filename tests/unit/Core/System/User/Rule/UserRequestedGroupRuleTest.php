<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupDefinition;
use Contena\Core\System\User\Aggregate\UserGroup\UserGroupEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Rule\UserRequestedGroupRule;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(UserRequestedGroupRule::class)]
#[Group('rules')]
class UserRequestedGroupRuleTest extends TestCase
{
    private UserRequestedGroupRule $rule;

    protected function setUp(): void
    {
        $this->rule = new UserRequestedGroupRule();
    }

    public function testName(): void
    {
        static::assertSame('userRequestedGroup', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $constraints, 'Constraint operator not found in Rule');
        static::assertArrayHasKey('userGroupIds', $constraints, 'Constraint userGroupIds not found in Rule');
        static::assertEquals(RuleConstraints::uuidOperators(), $constraints['operator']);
        static::assertEquals(RuleConstraints::uuids(), $constraints['userGroupIds']);
    }

    public function testGetConstraintsOperatorEmpty(): void
    {
        $rule = new UserRequestedGroupRule(Rule::OPERATOR_EMPTY);
        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('operator', $constraints, 'Constraint operator not found in Rule');
        static::assertArrayNotHasKey('userGroupIds', $constraints, 'Constraint userGroupIds found in Rule');
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
                'userGroupIds' => [
                    'name' => 'userGroupIds',
                    'type' => 'multi-entity-id-select',
                    'config' => [
                        'entity' => UserGroupDefinition::ENTITY_NAME,
                    ],
                ],
            ],
        ], $config->getData());
    }

    /**
     * @param array<string> $userGroupIds
     */
    #[DataProvider('getMatchValues')]
    public function testUserRequestedGroupRuleMatching(bool $expected, bool $loggedIn, ?string $requestedGroupId, array $userGroupIds, string $operator): void
    {
        $user = null;

        if ($loggedIn) {
            $user = new UserEntity();

            if ($requestedGroupId !== null) {
                $userGroup = new UserGroupEntity();
                $userGroup->setId($requestedGroupId);
                $user->setRequestedGroup($userGroup);
                $user->setRequestedGroupId($requestedGroupId);
            }
        }

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['userGroupIds' => $userGroupIds, 'operator' => $operator]);

        static::assertSame($expected, $this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = static::createStub(RuleScope::class);
        $this->rule->assign(['userGroupIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return \Traversable<string, array{expected: bool, loggedIn: bool, requestedGroupId: string|null, userGroupIds: array<string>, operator: string}>
     */
    public static function getMatchValues(): \Traversable
    {
        $id = Uuid::randomHex();

        yield 'operator_one_of / no match' => [
            'expected' => false,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'userGroupIds' => [Uuid::randomHex()],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'operator_one_of / one match' => [
            'expected' => true,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'userGroupIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'operator_one_of / empty' => [
            'expected' => false,
            'loggedIn' => true,
            'requestedGroupId' => null,
            'userGroupIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'operator_one_of / not logged in' => [
            'expected' => false,
            'loggedIn' => false,
            'requestedGroupId' => null,
            'userGroupIds' => [$id],
            'operator' => Rule::OPERATOR_EQ,
        ];

        yield 'operator_none_of / no match' => [
            'expected' => true,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'userGroupIds' => [Uuid::randomHex()],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'operator_none_of / one match' => [
            'expected' => false,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'userGroupIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'operator_none_of / empty' => [
            'expected' => true,
            'loggedIn' => true,
            'requestedGroupId' => null,
            'userGroupIds' => [$id, Uuid::randomHex()],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'operator_none_of / not logged in' => [
            'expected' => true,
            'loggedIn' => false,
            'requestedGroupId' => null,
            'userGroupIds' => [$id],
            'operator' => Rule::OPERATOR_NEQ,
        ];

        yield 'operator_empty / empty' => [
            'expected' => true,
            'loggedIn' => true,
            'requestedGroupId' => null,
            'userGroupIds' => [],
            'operator' => Rule::OPERATOR_EMPTY,
        ];

        yield 'operator_empty / not empty' => [
            'expected' => false,
            'loggedIn' => true,
            'requestedGroupId' => $id,
            'userGroupIds' => [],
            'operator' => Rule::OPERATOR_EMPTY,
        ];

        yield 'operator_empty / not logged in' => [
            'expected' => true,
            'loggedIn' => false,
            'requestedGroupId' => null,
            'userGroupIds' => [],
            'operator' => Rule::OPERATOR_EMPTY,
        ];
    }
}
