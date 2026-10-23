<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Rule\UserLoggedInRule;
use Contena\Core\System\User\UserEntity;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(UserLoggedInRule::class)]
#[Group('rules')]
class UserLoggedInRuleTest extends TestCase
{
    public function testGetConstraints(): void
    {
        $rule = new UserLoggedInRule();
        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('isLoggedIn', $constraints, 'Constraint isLoggedIn not found in Rule');
        static::assertEquals($constraints['isLoggedIn'], [
            new NotNull(),
            new Type(type: 'bool'),
        ]);
    }

    public function testName(): void
    {
        $rule = new UserLoggedInRule();
        static::assertSame('userLoggedIn', $rule->getName());
    }

    public function testGetConfig(): void
    {
        $rule = new UserLoggedInRule();
        $config = $rule->getConfig();
        static::assertEquals([
            'fields' => [
                'isLoggedIn' => [
                    'name' => 'isLoggedIn',
                    'type' => 'bool',
                    'config' => [],
                ],
            ],
            'operatorSet' => null,
        ], $config->getData());
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $rule = new UserLoggedInRule();

        $scope = static::createStub(TestRuleScope::class);

        $match = $rule->match($scope);

        static::assertFalse($match);
    }

    #[DataProvider('getCaseTestMatchValues')]
    public function testMatch(bool $isLoggedIn, bool $hasUser, bool $isMatching): void
    {
        $rule = new UserLoggedInRule($isLoggedIn);
        $channelContext = $this->createMock(ChannelContext::class);
        $channelContext->expects($this->once())
            ->method('getUser')
            ->willReturn($hasUser ? new UserEntity() : null);

        $scope = new CheckoutRuleScope($channelContext);
        $match = $rule->match($scope);
        static::assertSame($match, $isMatching);
    }

    public static function getCaseTestMatchValues(): \Generator
    {
        yield 'Condition is not logged in => Not match because admin_user logged in' => [
            false,
            true,
            false,
        ];

        yield 'Condition logged in => Not match because admin_user not logged in' => [
            true,
            false,
            false,
        ];

        yield 'Condition is not logged in => Match because admin_user not logged in' => [
            false,
            false,
            true,
        ];

        yield 'Condition is logged in => Match because admin_user logged in' => [
            true,
            true,
            true,
        ];
    }
}
