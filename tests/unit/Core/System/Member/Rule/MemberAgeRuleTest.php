<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberException;
use Contena\Core\System\Member\Rule\MemberAgeRule;
use Contena\Core\Test\Generator;

/**
 * @internal
 */
#[CoversClass(MemberAgeRule::class)]
#[Group('rules')]
class MemberAgeRuleTest extends TestCase
{
    private MemberAgeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new MemberAgeRule();
    }

    public function testGetName(): void
    {
        static::assertSame('memberAge', $this->rule->getName());
    }

    public function testInvalidCombinationOfValueAndOperator(): void
    {
        $this->expectException(MemberException::class);
        $this->rule->assign([
            'operator' => Rule::OPERATOR_EQ,
            'age' => null,
        ]);

        $member = new MemberEntity();

        $this->rule->match(new ChannelRuleScope(Generator::generateChannelContext(member: $member)));
    }

    #[DataProvider('getCaseTestMatchValues')]
    public function testIfMatchesCorrect(
        ?string $birthday,
        string $operator,
        ?int $age,
        bool $expected
    ): void {
        $this->rule->assign([
            'operator' => $operator,
            'age' => $age,
        ]);

        $member = new MemberEntity();

        if ($birthday) {
            $birthday = new \DateTimeImmutable($birthday);
            $member->setBirthday($birthday);
        }

        $match = $this->rule->match(new ChannelRuleScope(Generator::generateChannelContext(member: $member)));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getCaseTestMatchValues(): \Traversable
    {
        $birthday = new \DateTime('1991/10/16');
        $now = new \DateTime();

        $correctAge = $now->diff($birthday)->y;
        $wrongAge = $correctAge - 2;

        yield 'equal / match' => ['1991/10/16', Rule::OPERATOR_EQ, $correctAge, true];
        yield 'equal / no match' => ['1991/10/16', Rule::OPERATOR_EQ, $wrongAge, false];
        yield 'equal / fallback no match' => [null, Rule::OPERATOR_EQ, $correctAge, false];
        yield 'not equal / match' => ['1991/10/16', Rule::OPERATOR_NEQ, $wrongAge, true];
        yield 'not equal / no match' => ['1991/10/16', Rule::OPERATOR_NEQ, $correctAge, false];
        yield 'not equal / fallback match' => [null, Rule::OPERATOR_NEQ, $correctAge, true];
    }
}
