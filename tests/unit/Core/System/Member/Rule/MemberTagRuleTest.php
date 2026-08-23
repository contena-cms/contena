<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Member\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelRuleScope;
use Contena\Core\System\Member\MemberEntity;
use Contena\Core\System\Member\Rule\MemberTagRule;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(MemberTagRule::class)]
#[Group('rules')]
class MemberTagRuleTest extends TestCase
{
    private MemberTagRule $rule;

    protected function setUp(): void
    {
        $this->rule = new MemberTagRule();
    }

    public function testRuleConfig(): void
    {
        $expectedConfiguration = [
            'operatorSet' => [
                'operators' => [
                    Rule::OPERATOR_EQ,
                    Rule::OPERATOR_NEQ,
                    Rule::OPERATOR_EMPTY,
                ],
                'isMatchAny' => 0,
            ],
            'fields' => [
                'identifiers' => [
                    'name' => 'identifiers',
                    'type' => 'multi-entity-id-select',
                    'config' => [
                        'entity' => 'tag',
                    ],
                ],
            ],
        ];

        $data = $this->rule->getConfig()->getData();
        static::assertEquals($expectedConfiguration, $data);
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertEquals([
            'operator' => RuleConstraints::uuidOperators(),
            'identifiers' => RuleConstraints::uuids(),
        ], $constraints);
    }

    public function testConstraintsForEmptyOperator(): void
    {
        $this->rule->assign(['operator' => Rule::OPERATOR_EMPTY]);

        static::assertEquals([
            'operator' => RuleConstraints::uuidOperators(),
        ], $this->rule->getConstraints());
    }

    public function testConstraintsRejectEmptyIdentifiers(): void
    {
        $violations = $this->validateConstraint('identifiers', []);

        $this->assertViolationCode($violations, NotBlank::IS_BLANK_ERROR);
    }

    public function testConstraintsRejectStringIdentifiers(): void
    {
        $violations = $this->validateConstraint('identifiers', 'TAG-ID');

        $this->assertViolationCode($violations, Type::INVALID_TYPE_ERROR);
    }

    public function testConstraintsRejectInvalidIdentifierUuid(): void
    {
        $violations = $this->validateConstraint('identifiers', ['TAG-ID']);

        $this->assertViolationCode($violations, ArrayOfUuid::INVALID_TYPE_CODE);
    }

    public function testConstraintsAcceptValidIdentifiers(): void
    {
        $violations = $this->validateConstraint('identifiers', [Uuid::randomHex()]);

        static::assertCount(0, $violations);
    }

    /**
     * @param string|list<string>|null $givenIdentifier
     * @param array<string> $ruleIdentifiers
     */
    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, array $ruleIdentifiers, array|string|null $givenIdentifier, bool $noMember = false): void
    {
        $member = new MemberEntity();

        /** @var list<string> $memberIdentifiers */
        $memberIdentifiers = array_filter(\is_array($givenIdentifier) ? $givenIdentifier : [$givenIdentifier]);
        $member->setTagIds($memberIdentifiers);

        if ($noMember) {
            $member = null;
        }

        $scope = $this->createScope($member);
        $this->rule->assign(['identifiers' => $ruleIdentifiers, 'operator' => $operator]);

        $match = $this->rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return \Traversable<list<mixed>>
     */
    public static function getMatchValues(): \Traversable
    {
        yield 'operator_eq / not match / identifier' => [Rule::OPERATOR_EQ, false, ['kyln123', 'kyln456'], 'kyln000'];
        yield 'operator_eq / match partly / identifier' => [Rule::OPERATOR_EQ, true, ['kyln123', 'kyln456'], 'kyln123'];
        yield 'operator_eq / match full / identifier' => [Rule::OPERATOR_EQ, true, ['kyln123', 'kyln456'], ['kyln123', 'kyln456']];
        yield 'operator_eq / no match / no member' => [Rule::OPERATOR_EQ, false, ['kyln123', 'kyln456'], 'kyln123', true];
        yield 'operator_neq / match / identifier' => [Rule::OPERATOR_NEQ, true, ['kyln123', 'kyln456'], 'kyln000'];
        yield 'operator_neq / not match / identifier' => [Rule::OPERATOR_NEQ, false, ['kyln123', 'kyln456'], 'kyln123'];
        yield 'operator_empty / not match / identifier' => [Rule::OPERATOR_NEQ, false, ['kyln123', 'kyln456'], 'kyln123'];
        yield 'operator_empty / match / identifier' => [Rule::OPERATOR_EMPTY, true, ['kyln123', 'kyln456'], null];
        yield 'operator_neq / match / no member' => [Rule::OPERATOR_NEQ, true, ['kyln123', 'kyln456'], 'kyln123', true];
        yield 'operator_empty / match / no member' => [Rule::OPERATOR_EMPTY, true, ['kyln123', 'kyln456'], 'kyln123', true];
    }

    public function createScope(?MemberEntity $member): ChannelRuleScope
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getMember')->willReturn($member);

        return new ChannelRuleScope($context);
    }

    private function validateConstraint(string $field, mixed $value): ConstraintViolationListInterface
    {
        return Validation::createValidator()->validate($value, new MemberTagRule()->getConstraints()[$field]);
    }

    private function assertViolationCode(ConstraintViolationListInterface $violations, string $expectedCode, int $expectedCount = 1): void
    {
        static::assertCount($expectedCount, $violations);

        foreach ($violations as $violation) {
            static::assertSame($expectedCode, $violation->getCode());
        }
    }
}
