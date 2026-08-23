<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Rule\MemberCreatedByAdminRule;
use Contena\Core\Test\Generator;
use Symfony\Component\Validator\Constraints\NotNull;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(MemberCreatedByAdminRule::class)]
#[Group('rules')]
class MemberCreatedByAdminRuleTest extends TestCase
{
    public function testGetConstraints(): void
    {
        $rule = new MemberCreatedByAdminRule();
        $constraints = $rule->getConstraints();

        static::assertArrayHasKey('shouldMemberBeCreatedByAdmin', $constraints, 'Constraint shouldMemberBeCreatedByAdmin not found in Rule');
        static::assertEquals($constraints['shouldMemberBeCreatedByAdmin'], [
            new NotNull(),
            new Type(type: 'bool'),
        ]);
    }

    public function testName(): void
    {
        $rule = new MemberCreatedByAdminRule();
        static::assertSame('memberCreatedByAdmin', $rule->getName());
    }

    public function testGetConfig(): void
    {
        $rule = new MemberCreatedByAdminRule();
        $config = $rule->getConfig();
        static::assertEquals([
            'fields' => [
                'shouldMemberBeCreatedByAdmin' => [
                    'name' => 'shouldMemberBeCreatedByAdmin',
                    'type' => 'bool',
                    'config' => [],
                ],
            ],
            'operatorSet' => null,
        ], $config->getData());
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $rule = new MemberCreatedByAdminRule();
        $scope = static::createStub(TestRuleScope::class);

        $match = $rule->match($scope);

        static::assertFalse($match);
    }

    public function testMatchWithMissingMember(): void
    {
        $rule = new MemberCreatedByAdminRule();
        $channelContext = Generator::generateChannelContext();

        $scope = new ChannelRuleScope($channelContext);
        $match = $rule->match($scope);
        static::assertFalse($match);
    }

    #[DataProvider('getCaseTestMatchValues')]
    public function testMatch(MemberCreatedByAdminRule $rule, MemberEntity $member, bool $isMatching): void
    {
        $channelContext = Generator::generateChannelContext(member: $member);

        $scope = new ChannelRuleScope($channelContext);
        $match = $rule->match($scope);
        static::assertSame($match, $isMatching);
    }

    public static function getCaseTestMatchValues(): \Generator
    {
        yield 'Condition is not created by admin => Not match because member created by admin' => [
            new MemberCreatedByAdminRule(false),
            new MemberEntity()->assign(['createdById' => Uuid::randomHex()]),
            false,
        ];

        yield 'Condition is created by admin => Not match because member is registered' => [
            new MemberCreatedByAdminRule(true),
            new MemberEntity(),
            false,
        ];

        yield 'Condition is not created by admin => Match because member registered' => [
            new MemberCreatedByAdminRule(false),
            new MemberEntity(),
            true,
        ];

        yield 'Condition is created by admin => Match because user created by admin' => [
            new MemberCreatedByAdminRule(true),
            new MemberEntity()->assign(['createdById' => Uuid::randomHex()]),
            true,
        ];
    }
}
