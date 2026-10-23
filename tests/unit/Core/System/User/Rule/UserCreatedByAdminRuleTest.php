<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Rule\UserCreatedByAdminRule;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(UserCreatedByAdminRule::class)]
#[Group('rules')]
class UserCreatedByAdminRuleTest extends TestCase
{
    public function testGetConstraints(): void
    {
        $rule = new UserCreatedByAdminRule();
        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('shouldUserBeCreatedByAdmin', $constraints, 'Constraint shouldUserBeCreatedByAdmin not found in Rule');
        static::assertEquals($constraints['shouldUserBeCreatedByAdmin'], [
            new NotNull(),
            new Type(type: 'bool'),
        ]);
    }

    public function testName(): void
    {
        $rule = new UserCreatedByAdminRule();
        static::assertSame('userCreatedByAdmin', $rule->getName());
    }

    public function testGetConfig(): void
    {
        $rule = new UserCreatedByAdminRule();
        $config = $rule->getConfig();
        static::assertEquals([
            'fields' => [
                'shouldUserBeCreatedByAdmin' => [
                    'name' => 'shouldUserBeCreatedByAdmin',
                    'type' => 'bool',
                    'config' => [],
                ],
            ],
            'operatorSet' => null,
        ], $config->getData());
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $rule = new UserCreatedByAdminRule();
        $scope = static::createStub(TestRuleScope::class);

        $match = $rule->match($scope);

        static::assertFalse($match);
    }

    public function testMatchWithMissingUser(): void
    {
        $rule = new UserCreatedByAdminRule();
        $channelContext = $this->createMock(ChannelContext::class);
        $user = new UserEntity();
        $user->assign(['createdById' => Uuid::randomHex()]);

        $channelContext->expects($this->once())
            ->method('getUser')
            ->willReturn(null);

        $scope = new CheckoutRuleScope($channelContext);
        $match = $rule->match($scope);
        static::assertFalse($match);
    }

    #[DataProvider('getCaseTestMatchValues')]
    public function testMatch(UserCreatedByAdminRule $rule, UserEntity $user, bool $isMatching): void
    {
        $channelContext = $this->createMock(ChannelContext::class);

        $channelContext->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $scope = new CheckoutRuleScope($channelContext);
        $match = $rule->match($scope);
        static::assertSame($match, $isMatching);
    }

    public static function getCaseTestMatchValues(): \Generator
    {
        yield 'Condition is not created by admin => Not match because user created by admin' => [
            new UserCreatedByAdminRule(false),
            (new UserEntity())->assign(['createdById' => Uuid::randomHex()]),
            false,
        ];

        yield 'Condition is created by admin => Not match because user is registered' => [
            new UserCreatedByAdminRule(true),
            new UserEntity(),
            false,
        ];

        yield 'Condition is not created by admin => Match because user registered' => [
            new UserCreatedByAdminRule(false),
            new UserEntity(),
            true,
        ];

        yield 'Condition is created by admin => Match because admin_user created by admin' => [
            new UserCreatedByAdminRule(true),
            (new UserEntity())->assign(['createdById' => Uuid::randomHex()]),
            true,
        ];
    }
}
