<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Rule\Container\DaysSinceRule;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\User\Rule\DaysSinceFirstLoginRule;
use Contena\Core\System\User\Rule\DaysSinceLastLoginRule;
use Contena\Core\System\User\Rule\UserRuleScope;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;

/**
 * @internal
 */
#[CoversClass(DaysSinceFirstLoginRule::class)]
#[CoversClass(DaysSinceLastLoginRule::class)]
#[CoversClass(UserRuleScope::class)]
#[CoversClass(DaysSinceRule::class)]
class UserLoginRuleTest extends TestCase
{
    protected function setUp(): void
    {
        Clock::set(new MockClock('2026-08-03 12:00:00+00:00'));
    }

    protected function tearDown(): void
    {
        Clock::set(new NativeClock());
    }

    public function testDaysSinceFirstLoginUsesTheUserFromTheScope(): void
    {
        $user = new UserEntity();
        $user->setFirstLogin(new \DateTimeImmutable('2026-08-01 09:00:00+00:00'));
        $rule = new DaysSinceFirstLoginRule();
        $rule->assign(['operator' => Rule::OPERATOR_EQ, 'daysPassed' => 2]);

        static::assertTrue($rule->match(new UserRuleScope(Context::createDefaultContext(), $user)));
        static::assertSame('userDaysSinceFirstLogin', $rule->getName());
    }

    public function testDaysSinceLastLoginUsesTheUserFromTheScope(): void
    {
        $user = new UserEntity();
        $user->setLastLogin(new \DateTimeImmutable('2026-08-02 18:00:00+00:00'));
        $rule = new DaysSinceLastLoginRule();
        $rule->assign(['operator' => Rule::OPERATOR_EQ, 'daysPassed' => 1]);

        static::assertTrue($rule->match(new UserRuleScope(Context::createDefaultContext(), $user)));
        static::assertSame('userDaysSinceLastLogin', $rule->getName());
    }

    public function testUserLoginRulesDoNotMatchTheGenericScope(): void
    {
        $rule = new DaysSinceLastLoginRule();
        $rule->assign(['operator' => Rule::OPERATOR_EQ, 'daysPassed' => 1]);

        static::assertFalse($rule->match(new RuleScope(Context::createDefaultContext())));
    }
}
