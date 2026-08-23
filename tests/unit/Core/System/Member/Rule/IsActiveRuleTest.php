<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Rule\IsActiveRule;
use Contena\Core\Test\Generator;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(IsActiveRule::class)]
#[Group('rules')]
class IsActiveRuleTest extends TestCase
{
    private IsActiveRule $rule;

    protected function setUp(): void
    {
        $this->rule = new IsActiveRule();
    }

    #[DataProvider('getMemberScopeTestData')]
    public function testValidateRule(
        bool $isActive,
        bool $memberActiveValue,
        bool $expectedValue,
        bool $noMember
    ): void {
        $member = null;
        if (!$noMember) {
            $member = new MemberEntity();
            $member->setActive($memberActiveValue);
        }

        $isActiveMemberRule = new IsActiveRule($isActive);

        $scope = new ChannelRuleScope(Generator::generateChannelContext(member: $member));

        static::assertSame($expectedValue, $isActiveMemberRule->match($scope));
    }

    public function testConstrains(): void
    {
        $actualConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('isActive', $actualConstraints, 'Constrains not found in rule, given "isActive"');

        $isActiveConstraint = $actualConstraints['isActive'];

        static::assertEquals(new NotNull(), $isActiveConstraint[0]);
        static::assertEquals(new Type('bool'), $isActiveConstraint[1]);
    }

    public function testReturnsFalseWhenProvidingIncorrectScope(): void
    {
        $isActiveMemberRule = new IsActiveRule(true);

        $scope = new RuleScope(Context::createDefaultContext());

        static::assertFalse($isActiveMemberRule->match($scope));
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getMemberScopeTestData(): \Traversable
    {
        yield 'match / operator yes / active member' => [true, true, true, false];
        yield 'match / operator no / deactivated member' => [false, false, true, false];
        yield 'no match / operator yes / deactivated member' => [true, false, false, false];
        yield 'no match / operator no / active member' => [false, true, false, false];
        yield 'no match / operator yes / no member' => [true, false, false, true];
        yield 'no match / operator no / no member' => [false, false, false, true];
    }
}
