<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Rule\MemberLoggedInRule;
use Contena\Core\Test\Generator;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(MemberLoggedInRule::class)]
#[Group('rules')]
class MemberLoggedInRuleTest extends TestCase
{
    public function testGetConstraints(): void
    {
        $rule = new MemberLoggedInRule();
        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('isLoggedIn', $constraints, 'Constraint isLoggedIn not found in Rule');
        static::assertEquals($constraints['isLoggedIn'], [
            new NotNull(),
            new Type(type: 'bool'),
        ]);
    }

    public function testName(): void
    {
        $rule = new MemberLoggedInRule();
        static::assertSame('memberLoggedIn', $rule->getName());
    }

    public function testGetConfig(): void
    {
        $rule = new MemberLoggedInRule();
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
        $rule = new MemberLoggedInRule();

        $scope = static::createStub(TestRuleScope::class);

        $match = $rule->match($scope);

        static::assertFalse($match);
    }

    #[DataProvider('getCaseTestMatchValues')]
    public function testMatch(bool $isLoggedIn, bool $hasMember, bool $isMatching): void
    {
        $rule = new MemberLoggedInRule($isLoggedIn);
        $channelContext = Generator::generateChannelContext(member: $hasMember ? new MemberEntity() : null);

        $scope = new ChannelRuleScope($channelContext);
        $match = $rule->match($scope);
        static::assertSame($match, $isMatching);
    }

    public static function getCaseTestMatchValues(): \Generator
    {
        yield 'Condition is not logged in => Not match because user logged in' => [
            false,
            true,
            false,
        ];

        yield 'Condition logged in => Not match because user not logged in' => [
            true,
            false,
            false,
        ];

        yield 'Condition is not logged in => Match because user not logged in' => [
            false,
            false,
            true,
        ];

        yield 'Condition is logged in => Match because user logged in' => [
            true,
            true,
            true,
        ];
    }
}
