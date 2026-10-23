<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\LineItem\LineItem;
use Contena\Core\Checkout\Cart\Rule\LineItemScope;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Rule\IsActiveRule;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(IsActiveRule::class)]
#[Group('rules')]
class IsActiveRuleTest extends TestCase
{
    private IsActiveRule $rule;

    protected function setUp(): void
    {
        $this->rule = new IsActiveRule();
    }

    #[DataProvider('getUserScopeTestData')]
    public function testValidateRule(
        bool $isActive,
        bool $userActiveValue,
        bool $expectedValue,
        bool $noUser
    ): void {
        $channelContext = static::createStub(ChannelContext::class);

        if (!$noUser) {
            $user = new UserEntity();
            $user->setActive($userActiveValue);

            $channelContext
                ->method('getUser')
                ->willReturn($user);
        }

        $isActiveUserRule = new IsActiveRule($isActive);

        $scope = new CheckoutRuleScope($channelContext);

        static::assertSame($expectedValue, $isActiveUserRule->match($scope));
    }

    public function testConstrains(): void
    {
        $actualConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('isActive', $actualConstraints, 'Constrains not found in rule, given "isActive"');

        $isActiveConstraint = $actualConstraints['isActive'];

        static::assertEquals(new NotNull(), $isActiveConstraint[0]);
        static::assertEquals(new Type('bool'), $isActiveConstraint[1]);
    }

    public function testReturnsFalseWhenProvidingIncorrectScope(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $lineItem = new LineItem('random-id', 'line-item');

        $isActiveUserRule = new IsActiveRule(true);

        $scope = new LineItemScope($lineItem, $channelContext);

        static::assertFalse($isActiveUserRule->match($scope));
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getUserScopeTestData(): \Traversable
    {
        yield 'match / operator yes / active user' => [true, true, true, false];
        yield 'match / operator no / deactivated user' => [false, false, true, false];
        yield 'no match / operator yes / deactivated user' => [true, false, false, false];
        yield 'no match / operator no / active user' => [false, true, false, false];
        yield 'no match / operator yes / no user' => [true, false, false, true];
        yield 'no match / operator no / no user' => [false, false, false, true];
    }
}
