<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Rule\CampaignCodeRule;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Exception\UnsupportedValueException;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(CampaignCodeRule::class)]
#[Group('rules')]
class CampaignCodeRuleTest extends TestCase
{
    private CampaignCodeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new CampaignCodeRule();
    }

    public function testName(): void
    {
        static::assertSame('userCampaignCode', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('campaignCode', $constraints, 'Constraint campaignCode not found in Rule');
        static::assertArrayHasKey('operator', $constraints, 'Constraint operator not found in Rule');

        static::assertEquals(RuleConstraints::stringOperators(), $constraints['operator']);
        static::assertEquals(RuleConstraints::string(), $constraints['campaignCode']);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(?string $campaignCode, string $operator, ?string $campaignCodeConditionValue, bool $expected): void
    {
        $this->rule->assign([
            'operator' => $operator,
            'campaignCode' => $campaignCodeConditionValue,
        ]);

        $channelContext = static::createStub(ChannelContext::class);
        $user = new UserEntity();
        $user->setCampaignCode($campaignCode);

        $channelContext->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($channelContext);

        static::assertSame($expected, $this->rule->match($scope));
    }

    public function testInvalidCombinationOfValueAndOperator(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(UserException::class);
        }
        $user = new UserEntity();
        $user->setCampaignCode('code');

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        $this->rule->match($scope);
    }

    public function testEqualsOperatorIsNotMatchingWithoutUser(): void
    {
        $context = static::createStub(ChannelContext::class);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['campaignCode' => 'code', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testEmptyOperatorIsMatchingWithoutUser(): void
    {
        $context = static::createStub(ChannelContext::class);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['campaignCode' => 'code', 'operator' => Rule::OPERATOR_EMPTY]);
        static::assertTrue($this->rule->match($scope));
    }

    public function testInvalidScope(): void
    {
        $invalidScope = static::createStub(RuleScope::class);
        $this->rule->assign(['campaignCode' => 'code', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    public function testMatchThrowsException(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(UserException::class);
        }

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn(new UserEntity());

        (new CampaignCodeRule())->match(
            new CheckoutRuleScope($context)
        );
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getMatchValues(): \Traversable
    {
        yield 'equal operator is matching' => ['code a', Rule::OPERATOR_EQ, 'code a', true];
        yield 'equal operator is not matching' => ['code a', Rule::OPERATOR_EQ, 'code b', false];
        yield 'equal operator is not match, with empty user code ' => [null, Rule::OPERATOR_EQ, 'code a', false];

        yield 'not equal operator is matching' => ['code a', Rule::OPERATOR_NEQ, 'code b', true];
        yield 'not equal operator is not matching' => ['code a', Rule::OPERATOR_NEQ, 'code a', false];
        yield 'not equal operator is matching, with empty user code' => [null, Rule::OPERATOR_NEQ, 'code a', true];

        yield 'empty operator is matching, with empty user code' => [null, Rule::OPERATOR_EMPTY, 'code a', true];
        yield 'empty operator is not matching, with filled user code' => ['code a', Rule::OPERATOR_EMPTY, 'code a', false];
        yield 'empty operator is not matching, with empty rule code' => ['code a', Rule::OPERATOR_EMPTY, null, false];
    }
}
