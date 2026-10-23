<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\User\Rule\IsCompanyRule;
use Contena\Core\System\User\UserEntity;

/**
 * @internal
 */
#[CoversClass(IsCompanyRule::class)]
class IsCompanyUserRuleTest extends TestCase
{
    public function testThatNonExistingUserDoesNotMatch(): void
    {
        $this->matchRuleWithUser(new IsCompanyRule(true), null, false);
        $this->matchRuleWithUser(new IsCompanyRule(false), null, false);
    }

    public function testThatUserWithCompanyMatchesCorrectly(): void
    {
        $user = new UserEntity();
        $user->setCompany('Contena');

        $this->matchRuleWithUser(new IsCompanyRule(true), $user, true);
        $this->matchRuleWithUser(new IsCompanyRule(false), $user, false);
    }

    public function testThatUserWithoutCompanyMatchesCorrectly(): void
    {
        $user = new UserEntity();

        $this->matchRuleWithUser(new IsCompanyRule(true), $user, false);
        $this->matchRuleWithUser(new IsCompanyRule(false), $user, true);
    }

    public function testThatUserWithEmptyStringCompanyMatchesCorrectly(): void
    {
        $user = new UserEntity();
        $user->setCompany('');

        $this->matchRuleWithUser(new IsCompanyRule(true), $user, false);
        $this->matchRuleWithUser(new IsCompanyRule(false), $user, true);
    }

    private function matchRuleWithUser(IsCompanyRule $isCompanyRule, ?UserEntity $user, bool $isMatchExpected): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getUser')
            ->willReturn($user);

        $scope = new CheckoutRuleScope($channelContext);

        static::assertSame($isCompanyRule->match($scope), $isMatchExpected);
    }
}
