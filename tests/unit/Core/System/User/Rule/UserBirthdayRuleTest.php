<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Rule\LineItemScope;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Rule\UserBirthdayRule;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(UserBirthdayRule::class)]
#[Group('rules')]
class UserBirthdayRuleTest extends TestCase
{
    private UserBirthdayRule $rule;

    protected function setUp(): void
    {
        $this->rule = new UserBirthdayRule();
    }

    public function testName(): void
    {
        static::assertSame('userBirthday', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $operators = [
            Rule::OPERATOR_BETWEEN,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_GTE,
            Rule::OPERATOR_LTE,
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_GT,
            Rule::OPERATOR_LT,
            Rule::OPERATOR_EMPTY,
        ];

        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('birthday', $constraints, 'Birthday constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraints not found');

        static::assertEquals(new Type(type: 'string'), $constraints['birthday'][1]);
        static::assertEquals(new Choice(choices: $operators), $constraints['operator'][1]);
    }

    public function testBetweenConstraints(): void
    {
        $rule = new UserBirthdayRule(
            operator: Rule::OPERATOR_BETWEEN,
        );

        $constraints = $rule->getConstraints();

        static::assertEquals(
            RuleConstraints::dateBetween(),
            $constraints['birthday'],
        );
    }

    #[DataProvider('getMatchBirthdayValues')]
    public function testBirthdayRuleMatching(bool $expected, ?string $userBirthday, ?string $birthdayValue, string $operator): void
    {
        $user = new UserEntity();
        if ($userBirthday) {
            $user->setBirthday(new \DateTime($userBirthday));
        }

        $scope = $this->createScope($user);
        $this->rule->assign(['birthday' => $birthdayValue, 'operator' => $operator]);

        $isMatching = $this->rule->match($scope);

        static::assertSame($expected, $isMatching);
    }

    public function testUserWithoutBirthdayIsFalse(): void
    {
        $user = new UserEntity();

        $scope = $this->createScope($user);
        $this->rule->assign(['birthday' => '2000-09-05', 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    public function testUnsupportedValue(): void
    {
        $user = new UserEntity();

        $scope = $this->createScope($user);
        $this->rule->assign(['birthday' => null, 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidDateValueIsFalse(): void
    {
        $user = new UserEntity();
        $user->setBirthday(new \DateTime('2004-07-06'));

        $scope = $this->createScope($user);
        $this->rule->assign(['birthday' => 'invalid-date-value-string', 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    public function testUserNotExist(): void
    {
        $scope = new LineItemScope(
            new LineItem(Uuid::randomHex(), 'blog', null, 3),
            static::createStub(ChannelContext::class)
        );

        $this->rule->assign(['birthday' => '2000-09-05', 'operator' => Rule::OPERATOR_EQ]);
        $match = $this->rule->match($scope);

        static::assertFalse($match);
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = static::createStub(RuleScope::class);

        $this->rule->assign(['birthday' => '2000-09-05', 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($this->rule->match($invalidScope));
    }

    public function testConfig(): void
    {
        $config = (new UserBirthdayRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_DATE;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertSame([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $configData['operatorSet']);
    }

    /**
     * @return array<string, array{bool, string|null, string|null, string}>
     */
    public static function getMatchBirthdayValues(): array
    {
        return [
            'EQ - true' => [true, '2000-09-05', '2000-09-05', Rule::OPERATOR_EQ],
            'EQ - false' => [false, '2000-09-05', '2000-09-06', Rule::OPERATOR_EQ],
            'NEQ - true' => [true, '2000-09-05', '2000-09-06', Rule::OPERATOR_NEQ],
            'NEQ - false' => [false, '2000-09-05', '2000-09-05', Rule::OPERATOR_NEQ],
            'GT - true' => [true, '2000-09-06', '2000-09-05', Rule::OPERATOR_GT],
            'GT - false' => [false, '2000-09-05', '2000-09-06', Rule::OPERATOR_GT],
            'GTE - true' => [true, '2000-09-06', '2000-09-05', Rule::OPERATOR_GTE],
            'GTE - trueEQ' => [true, '2000-09-05', '2000-09-05', Rule::OPERATOR_GTE],
            'GTE - false' => [false, '2000-09-05', '2000-09-06', Rule::OPERATOR_GTE],
            'LT - true' => [true, '2000-09-05', '2000-09-06', Rule::OPERATOR_LT],
            'LT - false' => [false, '2000-09-06', '2000-09-05', Rule::OPERATOR_LT],
            'LTE - true' => [true, '2000-09-05', '2000-09-06', Rule::OPERATOR_LTE],
            'LTE - trueEQ' => [true, '2000-09-05', '2000-09-05', Rule::OPERATOR_LTE],
            'LTE - false' => [false, '2000-09-06', '2000-09-05', Rule::OPERATOR_LTE],
            'EMPTY - true' => [true, null, null, Rule::OPERATOR_EMPTY],
            'EMPTY - false' => [false, '2000-09-06', null, Rule::OPERATOR_EMPTY],
        ];
    }

    public function createScope(UserEntity $user): CheckoutRuleScope
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);

        return new CheckoutRuleScope($context);
    }
}
