<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberException;
use Contena\Core\System\Member\Rule\NameRule;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(NameRule::class)]
#[Group('rules')]
class NameRuleTest extends TestCase
{
    private NameRule $rule;

    protected function setUp(): void
    {
        $this->rule = new NameRule();
    }

    public function testName(): void
    {
        static::assertSame('memberName', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('name', $constraints, 'Name constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraints not found');

        static::assertEquals(RuleConstraints::stringOperators(), $constraints['operator']);
        static::assertEquals(RuleConstraints::string(), $constraints['name']);
    }

    #[DataProvider('getMatchMemberNameValues')]
    public function testNameRuleMatching(bool $expected, ?string $memberName, ?string $ruleNameValue, string $operator): void
    {
        $member = new MemberEntity();
        $member->setName($memberName ?? '');

        $scope = new ChannelRuleScope(Generator::generateChannelContext(member: $member));

        $this->rule->assign(['name' => $ruleNameValue, 'operator' => $operator]);

        static::assertSame($expected, $this->rule->match($scope));
    }

    public function testConfig(): void
    {
        $configData = new NameRule()->getConfig()->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertSame([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $configData['operatorSet']);
    }

    public function testMemberNotExist(): void
    {
        $scope = new ChannelRuleScope(Generator::generateChannelContext());

        $this->rule->assign(['name' => 'contena', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testMemberNotExistAndOperatorEmpty(): void
    {
        $scope = new ChannelRuleScope(Generator::generateChannelContext());

        $this->rule->assign(['name' => 'contena', 'operator' => Rule::OPERATOR_EMPTY]);
        static::assertTrue($this->rule->match($scope));
    }

    public function testInvalidName(): void
    {
        $member = new MemberEntity();
        $member->setName('contena');

        $scope = new ChannelRuleScope(Generator::generateChannelContext(member: $member));

        $this->rule->assign(['name' => true, 'operator' => Rule::OPERATOR_EQ]);

        $this->expectException(MemberException::class);
        $this->rule->match($scope);
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = new RuleScope(Context::createDefaultContext());
        $this->rule->assign(['name' => 'contena', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return array<string, array{bool, string|null, string|null, string}>
     */
    public static function getMatchMemberNameValues(): array
    {
        return [
            'EQ - true' => [true, 'contena', 'contena', Rule::OPERATOR_EQ],
            'EQ - false' => [false, 'contena', 'contenaAG', Rule::OPERATOR_EQ],
            'EQ(CASE) - true' => [true, 'contena', 'ConTena', Rule::OPERATOR_EQ],
            'NEQ - true' => [true, 'contena', 'contenaAG', Rule::OPERATOR_NEQ],
            'NEQ - false' => [false, 'contena', 'contena', Rule::OPERATOR_NEQ],
            'NEQ(CASE) - false' => [false, 'contena', 'ConTena', Rule::OPERATOR_NEQ],
            'EMPTY - false' => [false, 'contena', null, Rule::OPERATOR_EMPTY],
            'EMPTY - true' => [true, null, null, Rule::OPERATOR_EMPTY],
        ];
    }
}
