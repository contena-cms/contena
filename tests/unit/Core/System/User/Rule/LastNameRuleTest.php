<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Rule\CartRuleScope;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Rule\Exception\UnsupportedValueException;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Rule\LastNameRule;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;

/**
 * @internal
 */
#[CoversClass(LastNameRule::class)]
#[Group('rules')]
class LastNameRuleTest extends TestCase
{
    private LastNameRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LastNameRule();
    }

    public function testName(): void
    {
        static::assertSame('userLastName', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('lastName', $constraints, 'LastName constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraints not found');

        static::assertEquals(RuleConstraints::stringOperators(), $constraints['operator']);
        static::assertEquals(RuleConstraints::string(), $constraints['lastName']);
    }

    #[DataProvider('getMatchUserLastNameValues')]
    public function testLastNameRuleMatching(bool $expected, ?string $userName, ?string $ruleNameValue, string $operator): void
    {
        $user = new UserEntity();
        $user->setLastName($userName ?? '');

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $cart = new Cart('test');
        $scope = new CartRuleScope($cart, $context);

        $this->rule->assign(['lastName' => $ruleNameValue, 'operator' => $operator]);

        $isMatching = $this->rule->match($scope);

        static::assertSame($expected, $isMatching);
    }

    public function testConfig(): void
    {
        $config = (new LastNameRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertSame([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $configData['operatorSet']);
    }

    public function testUserNotExist(): void
    {
        $scope = new CartRuleScope(
            new Cart('test'),
            static::createStub(ChannelContext::class)
        );

        $this->rule->assign(['lastName' => 'contena', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testUserNotExistAndOperatorEmpty(): void
    {
        $scope = new CartRuleScope(
            new Cart('test'),
            static::createStub(ChannelContext::class)
        );

        $this->rule->assign(['lastName' => 'contena', 'operator' => Rule::OPERATOR_EMPTY]);
        static::assertTrue($this->rule->match($scope));
    }

    public function testInvalidLastName(): void
    {
        $user = new UserEntity();
        $user->setLastName('contena');

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $cart = new Cart('test');
        $scope = new CartRuleScope($cart, $context);

        $this->rule->assign(['lastName' => true, 'operator' => Rule::OPERATOR_EQ]);

        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(UserException::class);
        }
        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = static::createStub(RuleScope::class);
        $this->rule->assign(['lastName' => 'contena', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return array<string, array{bool, string|null, string|null, string}>
     */
    public static function getMatchUserLastNameValues(): array
    {
        return [
            'EQ - true' => [true, 'contena', 'contena', Rule::OPERATOR_EQ],
            'EQ - false' => [false, 'contena', 'contenaAG', Rule::OPERATOR_EQ],
            'EQ(CASE) - true' => [true, 'contena', 'ShopWare', Rule::OPERATOR_EQ],
            'NEQ - true' => [true, 'contena', 'contenaAG', Rule::OPERATOR_NEQ],
            'NEQ - false' => [false, 'contena', 'contena', Rule::OPERATOR_NEQ],
            'NEQ(CASE) - false' => [false, 'contena', 'ShopWare', Rule::OPERATOR_NEQ],
            'EMPTY - false' => [false, 'contena', null, Rule::OPERATOR_EMPTY],
            'EMPTY - true' => [true, null, null, Rule::OPERATOR_EMPTY],
        ];
    }
}
