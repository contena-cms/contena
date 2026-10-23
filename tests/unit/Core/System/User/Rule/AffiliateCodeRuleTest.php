<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Rule\AffiliateCodeRule;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Exception\UnsupportedValueException;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(AffiliateCodeRule::class)]
#[Group('rules')]
class AffiliateCodeRuleTest extends TestCase
{
    public function testGetConstraints(): void
    {
        $constraints = (new AffiliateCodeRule())->getConstraints();

        static::assertArrayHasKey('affiliateCode', $constraints, 'Constraint affiliateCode not found in Rule');
        static::assertEquals($constraints['affiliateCode'], [
            new NotBlank(),
            new Type(type: 'string'),
        ]);
    }

    public function testName(): void
    {
        $rule = new AffiliateCodeRule();
        static::assertSame('userAffiliateCode', $rule->getName());
    }

    public function testGetConfig(): void
    {
        $config = (new AffiliateCodeRule())->getConfig();
        static::assertEquals([
            'fields' => [
                'affiliateCode' => [
                    'name' => 'affiliateCode',
                    'type' => 'string',
                    'config' => [],
                ],
            ],
            'operatorSet' => [
                'operators' => [Rule::OPERATOR_EQ, Rule::OPERATOR_NEQ, Rule::OPERATOR_EMPTY],
                'isMatchAny' => false,
            ],
        ], $config->getData());
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $scope = static::createStub(TestRuleScope::class);

        $match = (new AffiliateCodeRule())->match($scope);

        static::assertFalse($match);
    }

    public function testInvalidCombinationOfValueAndOperator(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(UserException::class);
        }
        $rule = new AffiliateCodeRule(Rule::OPERATOR_EQ, null);
        $user = new UserEntity();
        $user->setAffiliateCode('testing');
        $channelContext = $this->createMock(ChannelContext::class);
        $channelContext->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $scope = new CheckoutRuleScope($channelContext);
        $rule->match($scope);
    }

    #[DataProvider('getCaseTestMatchValues')]
    public function testMatch(string $operator, ?string $ruleCode, ?string $userCode, bool $hasUser, bool $isMatching): void
    {
        $rule = new AffiliateCodeRule($operator, $ruleCode);
        $user = new UserEntity();
        $user->setAffiliateCode($userCode);
        $channelContext = $this->createMock(ChannelContext::class);
        $channelContext->expects($this->once())
            ->method('getUser')
            ->willReturn($hasUser ? $user : null);

        $scope = new CheckoutRuleScope($channelContext);
        $match = $rule->match($scope);
        static::assertSame($match, $isMatching);
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getCaseTestMatchValues(): \Traversable
    {
        yield 'Equals Operator is matching' => [
            Rule::OPERATOR_EQ,
            'testingCode',
            'testingCode',
            true,
            true,
        ];

        yield 'Equals Operator is not matching' => [
            Rule::OPERATOR_EQ,
            'testingCode',
            'otherCode',
            true,
            false,
        ];

        yield 'Not Equals Operator is matching' => [
            Rule::OPERATOR_NEQ,
            'testingCode',
            'otherCode',
            true,
            true,
        ];

        yield 'Not Equals Operator is not matching' => [
            Rule::OPERATOR_NEQ,
            'testingCode',
            'testingCode',
            true,
            false,
        ];

        yield 'Empty Operator is matching, because both codes not exists' => [
            Rule::OPERATOR_EMPTY,
            null,
            null,
            true,
            true,
        ];

        yield 'Empty Operator is matching, because user code not exists' => [
            Rule::OPERATOR_EMPTY,
            'testingCode',
            null,
            true,
            true,
        ];

        yield 'Empty Operator is matching, because user not exists' => [
            Rule::OPERATOR_EMPTY,
            'testingCode',
            null,
            false,
            true,
        ];

        yield 'Empty Operator is not matching, because both codes are filled' => [
            Rule::OPERATOR_EMPTY,
            'testingCode',
            'testingCode',
            true,
            false,
        ];

        yield 'Empty Operator is not matching, because user code is filled' => [
            Rule::OPERATOR_EMPTY,
            null,
            'testingCode',
            true,
            false,
        ];
    }
}
