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
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Rule\BillingCityRule;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(BillingCityRule::class)]
#[Group('rules')]
class BillingCityRuleTest extends TestCase
{
    private BillingCityRule $rule;

    protected function setUp(): void
    {
        $this->rule = new BillingCityRule();
    }

    public function testName(): void
    {
        static::assertSame('userBillingCity', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('cityName', $constraints, 'Constraint cityName not found in Rule');
        static::assertArrayHasKey('operator', $constraints, 'Constraint operator not found in Rule');

        static::assertEquals([new NotBlank(), new Choice(choices: [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_EMPTY,
        ])], $constraints['operator']);
        static::assertEquals([new NotBlank(), new Type('string')], $constraints['cityName']);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $billingCity): void
    {
        $cityName = 'kyln123';
        $channelContext = static::createStub(ChannelContext::class);
        $userAddress = new UserAddressEntity();
        $userAddress->setCity($billingCity);

        $user = new UserEntity();
        $user->setActiveBillingAddress($userAddress);
        $channelContext->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($channelContext);
        $this->rule->assign(['cityName' => $cityName, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    public function testValidationWithMissingCityName(): void
    {
        $user = new UserEntity();
        $user->setActiveBillingAddress(new UserAddressEntity());

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(UserException::class);
        }
        static::assertFalse($this->rule->match($scope));
    }

    public function testValidationWithEmptyCityName(): void
    {
        $user = new UserEntity();
        $address = new UserAddressEntity();
        $address->setCity('test');
        $user->setActiveBillingAddress($address);

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['cityName' => '', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testValidateWithInvalidCityNameType(): void
    {
        $user = new UserEntity();
        $address = new UserAddressEntity();
        $address->setCity('test');
        $user->setActiveBillingAddress($address);

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['cityName' => true, 'operator' => Rule::OPERATOR_EQ]);
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(UserException::class);
        }
        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidScope(): void
    {
        $invalidScope = static::createStub(RuleScope::class);
        $this->rule->assign(['cityName' => 'test', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    public function testMissingUser(): void
    {
        $context = static::createStub(ChannelContext::class);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['cityName' => 'test', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testMissingUserActiveAddress(): void
    {
        $user = new UserEntity();
        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['cityName' => 'test', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testMatchThrowsException(): void
    {
        if (!Feature::isActive('v6.8.0.0')) {
            $this->expectException(UnsupportedValueException::class);
        } else {
            $this->expectException(UserException::class);
        }
        $user = new UserEntity();
        $user->setActiveBillingAddress(new UserAddressEntity());
        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);

        (new BillingCityRule())->match(
            new CheckoutRuleScope($context)
        );
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function getMatchValues(): array
    {
        return [
            'operator_oq / not match / city' => [Rule::OPERATOR_EQ, false, 'kyln000'],
            'operator_oq / match / city' => [Rule::OPERATOR_EQ, true, 'kyln123'],
            'operator_neq / match / city' => [Rule::OPERATOR_NEQ, true, 'kyln000'],
            'operator_neq / not match / city' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / not match / city' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / match / city' => [Rule::OPERATOR_EMPTY, true, ' '],
        ];
    }
}
