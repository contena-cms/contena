<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\MemberException;
use Contena\Core\System\Member\Rule\EmailRule;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(EmailRule::class)]
#[Group('rules')]
class EmailRuleTest extends TestCase
{
    private EmailRule $rule;

    protected function setUp(): void
    {
        $this->rule = new EmailRule();
    }

    public function testRuleMatchThrowsAndExceptionWhenNoMemberEmailIsProvided(): void
    {
        $this->expectExceptionObject(MemberException::unsupportedValue(\gettype(null), EmailRule::class));

        $member = new MemberEntity();
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getMember')->willReturn($member);

        $scope = new ChannelRuleScope($channelContext);
        $this->rule->assign(['email' => null, 'operator' => Rule::OPERATOR_EQ]);

        $this->rule->match($scope);
    }

    public function testRuleMatchThrowsAndExceptionWhenOperatorIsNotSupported(): void
    {
        $this->expectExceptionObject(MemberException::unsupportedOperator(Rule::OPERATOR_LTE, EmailRule::class));

        $member = new MemberEntity();
        $member->setEmail('*');
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getMember')->willReturn($member);

        $scope = new ChannelRuleScope($channelContext);
        $this->rule->assign(['email' => '*', 'operator' => Rule::OPERATOR_LTE]);

        $this->rule->match($scope);
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice(choices: $expectedOperators), $operators[1]);

        static::assertArrayHasKey('email', $ruleConstraints, 'Constraint email not found in Rule');
        $email = $ruleConstraints['email'];
        static::assertEquals(new NotBlank(), $email[0]);
        static::assertEquals(new Type('string'), $email[1]);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, string $memberEmail, string $email, bool $expected, bool $noMember = false): void
    {
        $channelContext = static::createStub(ChannelContext::class);

        $member = new MemberEntity();
        $member->setEmail($memberEmail);

        if ($noMember) {
            $member = null;
        }

        $channelContext->method('getMember')->willReturn($member);
        $scope = new ChannelRuleScope($channelContext);
        $this->rule->assign(['email' => $email, 'operator' => $operator]);

        $match = $this->rule->match($scope);

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|bool>>
     */
    public static function getMatchValues(): \Traversable
    {
        // OPERATOR_EQ
        yield 'operator_eq / match exact / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'test@example.com', true];
        yield 'operator_eq / not match exact / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'foo@example.com', false];
        yield 'operator_eq / match partially between / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'te*@exa*le.com', true];
        yield 'operator_eq / match partially start / email' => [Rule::OPERATOR_EQ, 'test@example.com', '*@example.com', true];
        yield 'operator_eq / match partially end / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'test@*', true];
        yield 'operator_eq / not match partially between / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'foo@*.com', false];
        yield 'operator_eq / not match partially start / email' => [Rule::OPERATOR_EQ, 'test@example.com', '*@contena.cn', false];
        yield 'operator_eq / not match partially end / email' => [Rule::OPERATOR_EQ, 'test@example.com', 'foo@*', false];
        yield 'operator_eq / no match / no member' => [Rule::OPERATOR_EQ, 'test@example.com', 'test@example.com', false, true];

        // OPERATOR_NEQ
        yield 'operator_neq / not match exact / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'foo@example.com', true];
        yield 'operator_neq / match exact / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'test@example.com', false];
        yield 'operator_neq / match partially between / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'te*@exa*le.com', false];
        yield 'operator_neq / match partially start / email' => [Rule::OPERATOR_NEQ, 'test@example.com', '*@example.com', false];
        yield 'operator_neq / match partially end / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'test@*', false];
        yield 'operator_neq / not match partially between / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'foo@*.com', true];
        yield 'operator_neq / not match partially start / email' => [Rule::OPERATOR_NEQ, 'test@example.com', '*@contena.cn', true];
        yield 'operator_neq / not match partially end / email' => [Rule::OPERATOR_NEQ, 'test@example.com', 'foo@*', true];

        yield 'operator_neq / match / no member' => [Rule::OPERATOR_NEQ, 'test@example.com', 'test@example.com', true, true];
    }
}
