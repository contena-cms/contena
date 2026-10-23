<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Rule\Exception\UnsupportedValueException;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Rule\UserAgeRule;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;

/**
 * @internal
 */
#[CoversClass(UserAgeRule::class)]
#[Group('rules')]
class UserAgeRuleTest extends TestCase
{
    private UserAgeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new UserAgeRule();
    }

    public function testGetName(): void
    {
        static::assertSame('userAge', $this->rule->getName());
    }

    public function testInvalidCombinationOfValueAndOperator(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(UserException::class);
        }
        $this->rule->assign([
            'operator' => Rule::OPERATOR_EQ,
            'age' => null,
        ]);

        $channelContext = static::createStub(ChannelContext::class);

        $user = new UserEntity();
        $channelContext->method('getUser')->willReturn($user);

        $this->rule->match(new CheckoutRuleScope($channelContext));
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

        $channelContext = static::createStub(ChannelContext::class);

        $user = new UserEntity();

        if ($birthday) {
            $birthday = new \DateTimeImmutable($birthday);
            $user->setBirthday($birthday);
        }

        $channelContext->method('getUser')->willReturn($user);

        $match = $this->rule->match(new CheckoutRuleScope($channelContext));

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
