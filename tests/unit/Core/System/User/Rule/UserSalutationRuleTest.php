<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Cart;
use Contena\Core\Checkout\Cart\Rule\CartRuleScope;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Rule\UserSalutationRule;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConfig;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Salutation\SalutationEntity;

/**
 * @internal
 */
#[CoversClass(UserSalutationRule::class)]
#[Group('rules')]
class UserSalutationRuleTest extends TestCase
{
    private UserSalutationRule $rule;

    protected function setUp(): void
    {
        $this->rule = new UserSalutationRule();
    }

    public function testName(): void
    {
        static::assertSame('userSalutation', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('salutationIds', $constraints, 'salutation constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraint not found');

        static::assertEquals(RuleConstraints::uuids(), $constraints['salutationIds']);
        static::assertEquals(RuleConstraints::uuidOperators(), $constraints['operator']);
    }

    /**
     * @param list<string> $salutationIds
     */
    #[DataProvider('getMatchUserSalutationValues')]
    public function testUserSalutationRuleMatching(bool $expected, ?string $userSalutationId, array $salutationIds, string $operator): void
    {
        $user = new UserEntity();
        $salutation = new SalutationEntity();

        if ($userSalutationId) {
            $salutation->setId($userSalutationId);
            $user->setSalutation($salutation);
        }

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['salutationIds' => $salutationIds, 'operator' => $operator]);

        $isMatching = $this->rule->match($scope);
        static::assertSame($expected, $isMatching);
    }

    public function testConfig(): void
    {
        $config = (new UserSalutationRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;
        $operators[] = Rule::OPERATOR_EMPTY;

        static::assertSame([
            'operators' => $operators,
            'isMatchAny' => false,
        ], $configData['operatorSet']);
    }

    public function testUserNotLoggedInReturnsFalse(): void
    {
        $scope = new CartRuleScope(
            new Cart('test'),
            static::createStub(ChannelContext::class)
        );

        $this->rule->assign(['salutationIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testUserHasNoSalutationSpecifiedReturnsFalse(): void
    {
        $user = new UserEntity();

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['salutationIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = static::createStub(RuleScope::class);
        $this->rule->assign(['salutationIds' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return array<string, array{bool, string|null, list<string>, string}>
     */
    public static function getMatchUserSalutationValues(): array
    {
        $id = Uuid::randomHex();

        return [
            'ONE OF - true' => [true, $id, [$id, Uuid::randomHex()], Rule::OPERATOR_EQ],
            'ONE OF - false' => [false, $id, [Uuid::randomHex()], Rule::OPERATOR_EQ],
            'NONE OF - true' => [true, $id, [Uuid::randomHex()], Rule::OPERATOR_NEQ],
            'NONE OF - false' => [false, $id, [$id, Uuid::randomHex()], Rule::OPERATOR_NEQ],
            'EMPTY - true' => [true, null, [Uuid::randomHex()], Rule::OPERATOR_EMPTY],
            'EMPTY - false' => [false, $id, [Uuid::randomHex()], Rule::OPERATOR_EMPTY],
        ];
    }
}
