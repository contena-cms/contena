<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Rule\Container\DaysSinceRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleException;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Rule\DaysSinceLastLoginRule;
use Contena\Core\Test\Generator;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(DaysSinceLastLoginRule::class)]
#[CoversClass(DaysSinceRule::class)]
#[Group('rules')]
class DaysSinceLastLoginRuleTest extends TestCase
{
    protected DaysSinceLastLoginRule $rule;

    protected function setUp(): void
    {
        Clock::set(new MockClock(self::getTestTimestamp()));
        $this->rule = new DaysSinceLastLoginRule();
    }

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
    }

    public function testGetName(): void
    {
        static::assertSame('memberDaysSinceLastLogin', $this->rule->getName());
    }

    public function testInvalidCombinationOfValueAndOperator(): void
    {
        $this->expectException(RuleException::class);
        $this->rule->assign([
            'operator' => Rule::OPERATOR_EQ,
            'daysPassed' => null,
        ]);

        $member = new MemberEntity();

        $this->rule->match(new ChannelRuleScope(Generator::generateChannelContext(member: $member)));
    }

    #[DataProvider('getCaseTestMatchValues')]
    public function testIfMatchesCorrect(
        string $operator,
        bool $isMatching,
        float $daysPassed,
        ?\DateTimeImmutable $day,
        bool $noMember = false
    ): void {
        $member = new MemberEntity();
        $member->setLastLogin($day);

        if ($noMember) {
            $member = null;
        }
        $scope = new ChannelRuleScope(Generator::generateChannelContext(member: $member));

        $this->rule->assign([
            'operator' => $operator,
            'daysPassed' => $daysPassed,
        ]);

        $match = $this->rule->match($scope);

        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getCaseTestMatchValues(): \Traversable
    {
        $datetime = self::getTestTimestamp();

        $dayTest = $datetime->modify('-30 minutes');

        yield 'operator_eq / not match / day passed / day' => [Rule::OPERATOR_EQ, false, 1.2, $dayTest];
        yield 'operator_eq / match / day passed / day' => [Rule::OPERATOR_EQ, true, 0, $dayTest];
        yield 'operator_neq / match / day passed / day' => [Rule::OPERATOR_NEQ, true, 1, $dayTest];
        yield 'operator_neq / not match / day passed/ day' => [Rule::OPERATOR_NEQ, false, 0, $dayTest];
        yield 'operator_lte_lt / not match / day passed / day' => [Rule::OPERATOR_LTE, false, -1.1, $dayTest];
        yield 'operator_lte_lt / match / day passed/ day' => [Rule::OPERATOR_LTE, true, 1,  $dayTest];
        yield 'operator_lte_e / match / day passed/ day' => [Rule::OPERATOR_LTE, true, 0, $dayTest];
        yield 'operator_gte_gt / not match / day passed/ day' => [Rule::OPERATOR_GTE, false, 1, $dayTest];
        yield 'operator_gte_gt / match / day passed / day' => [Rule::OPERATOR_GTE, true, -1, $dayTest];
        yield 'operator_gte_e / match / day passed / day' => [Rule::OPERATOR_GTE, true, 0, $dayTest];
        yield 'operator_lt / not match / day passed / day' => [Rule::OPERATOR_LT, false, 0, $dayTest];
        yield 'operator_lt / match / day passed / day' => [Rule::OPERATOR_LT, true, 1,  $dayTest];
        yield 'operator_gt / not match / day passed / day' => [Rule::OPERATOR_GT, false, 1, $dayTest];
        yield 'operator_gt / match / day passed / day' => [Rule::OPERATOR_GT, true, -1, $dayTest];
        yield 'operator_empty / not match / day passed/ day' => [Rule::OPERATOR_EMPTY, false, 0, $dayTest];
        yield 'operator_empty / match / day passed / day' => [Rule::OPERATOR_EMPTY, true, 0, null];
        yield 'operator_eq / no match / no member' => [Rule::OPERATOR_EQ, false, 0, $dayTest, true];
        yield 'operator_neq / match / no member' => [Rule::OPERATOR_NEQ, true, 0, $dayTest, true];
        yield 'operator_empty / match / no member' => [Rule::OPERATOR_EMPTY, true, 0, $dayTest, true];
    }

    private static function getTestTimestamp(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2020-03-10T15:00:00+00:00');
    }
}
