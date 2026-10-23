<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Aggregate\UserAddress\UserAddressEntity;
use Contena\Core\System\User\Rule\BillingStateRule;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[CoversClass(BillingStateRule::class)]
#[Group('rules')]
class BillingStateRuleTest extends TestCase
{
    private BillingStateRule $rule;

    protected function setUp(): void
    {
        $this->rule = new BillingStateRule();
    }

    public function testName(): void
    {
        static::assertSame('userBillingState', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $constraints, 'Constraint operator not found in Rule');
        static::assertArrayHasKey('stateIds', $constraints, 'Constraint stateIds not found in Rule');

        static::assertEquals([new NotBlank(), new Choice(choices: [Rule::OPERATOR_EQ, Rule::OPERATOR_NEQ, Rule::OPERATOR_EMPTY])], $constraints['operator']);
        static::assertEquals([new NotBlank(), new ArrayOfUuid()], $constraints['stateIds']);
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $stateId): void
    {
        $countryIds = ['kyln123', 'kyln456'];
        $channelContext = static::createStub(ChannelContext::class);
        $userAddress = new UserAddressEntity();
        $userAddress->setCountryStateId($stateId);
        $user = new UserEntity();

        $user->setActiveBillingAddress($userAddress);
        $channelContext->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($channelContext);
        $this->rule->assign(['stateIds' => $countryIds, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    public function testValidationWithMissingStateIds(): void
    {
        $user = new UserEntity();
        $user->setActiveBillingAddress(new UserAddressEntity());

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testValidationWithEmptyStateIds(): void
    {
        $user = new UserEntity();
        $address = new UserAddressEntity();
        $address->setCountryStateId(Uuid::randomHex());
        $user->setActiveBillingAddress($address);

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['stateIds' => [], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testValidationWithInvalidStateIds(): void
    {
        $user = new UserEntity();
        $address = new UserAddressEntity();
        $address->setCountryStateId(Uuid::randomHex());
        $user->setActiveBillingAddress($address);

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['stateIds' => 'COUNTRY-ID-1', 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testValidationWithArrayOfInvalidStateIdTypes(): void
    {
        $user = new UserEntity();
        $address = new UserAddressEntity();
        $address->setCountryStateId(Uuid::randomHex());
        $user->setActiveBillingAddress($address);

        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn($user);
        $scope = new CheckoutRuleScope($context);

        $this->rule->assign(['stateIds' => ['STATE-ID-1', true, 3, Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidScope(): void
    {
        $invalidScope = static::createStub(RuleScope::class);
        $this->rule->assign(['stateIds' => ['id'], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    /**
     * @return array<string, array{string, bool, string}>
     */
    public static function getMatchValues(): array
    {
        return [
            'operator_oq / not match / state id' => [Rule::OPERATOR_EQ, false, Uuid::randomHex()],
            'operator_oq / match / state id' => [Rule::OPERATOR_EQ, true, 'kyln123'],
            'operator_neq / match / state id' => [Rule::OPERATOR_NEQ, true,  Uuid::randomHex()],
            'operator_neq / not match / state id' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / not match / state id' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
            'operator_empty / match / state id' => [Rule::OPERATOR_EMPTY, true, ''],
        ];
    }
}
