<?php

declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Language\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\Framework\Rule\RuleConstraints;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\Constraint\ArrayOfUuid;
use Contena\Core\System\Language\LanguageException;
use Contena\Core\System\Language\Rule\LanguageRule;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[CoversClass(LanguageRule::class)]
class LanguageRuleTest extends TestCase
{
    public function testConstraints(): void
    {
        $ruleConstraints = (new LanguageRule())->getConstraints();

        static::assertEquals([
            'operator' => RuleConstraints::uuidOperators(false),
            'languageIds' => RuleConstraints::uuids(),
        ], $ruleConstraints);
    }

    public function testConstraintsRejectEmptyLanguageIds(): void
    {
        $violations = $this->validateConstraint('languageIds', []);

        $this->assertViolationCode($violations, NotBlank::IS_BLANK_ERROR);
    }

    public function testConstraintsRejectInvalidLanguageIdsUuid(): void
    {
        $violations = $this->validateConstraint('languageIds', ['INVALID-UUID', true, 3]);

        $this->assertViolationCode($violations, ArrayOfUuid::INVALID_TYPE_CODE, 3);
    }

    public function testConstraintsAcceptValidLanguageIds(): void
    {
        $violations = $this->validateConstraint('languageIds', [Uuid::randomHex(), Uuid::randomHex()]);

        static::assertCount(0, $violations);
    }

    #[DataProvider('validUuidOperators')]
    public function testConstraintsAcceptAvailableOperators(string $operator): void
    {
        $violations = $this->validateConstraint('operator', $operator);

        static::assertCount(0, $violations);
    }

    #[DataProvider('invalidUuidOperators')]
    public function testConstraintsRejectInvalidOperators(string $operator): void
    {
        $violations = $this->validateConstraint('operator', $operator);

        $this->assertViolationCode($violations, Choice::NO_SUCH_CHOICE_ERROR);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function validUuidOperators(): \Generator
    {
        yield 'equals' => [Rule::OPERATOR_EQ];
        yield 'not equals' => [Rule::OPERATOR_NEQ];
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function invalidUuidOperators(): \Generator
    {
        yield 'less than or equals' => [Rule::OPERATOR_LTE];
        yield 'greater than or equals' => [Rule::OPERATOR_GTE];
        yield 'unknown operator' => ['Invalid'];
    }

    #[DataProvider('getMatchValues')]
    public function testRuleMatching(string $operator, bool $isMatching, string $languageId): void
    {
        $languageIds = ['kyln123', 'kyln456'];
        $channelContext = static::createStub(ChannelContext::class);
        $context = new Context(new SystemSource(), [], Defaults::CURRENCY, [$languageId]);

        $channelContext->method('getContext')->willReturn($context);
        $scope = new CheckoutRuleScope($channelContext);
        $rule = (new LanguageRule())->assign(['languageIds' => $languageIds, 'operator' => $operator]);

        $match = $rule->match($scope);
        if ($isMatching) {
            static::assertTrue($match);
        } else {
            static::assertFalse($match);
        }
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string}>
     */
    public static function getMatchValues(): array
    {
        return [
            'operator_eq / not match / language id' => [Rule::OPERATOR_EQ, false, Uuid::randomHex()],
            'operator_eq / match / language id' => [Rule::OPERATOR_EQ, true, 'kyln123'],
            'operator_neq / match / language id' => [Rule::OPERATOR_NEQ, true,  Uuid::randomHex()],
            'operator_neq / not match / language id' => [Rule::OPERATOR_NEQ, false, 'kyln123'],
        ];
    }

    public function testCallingMatchWithoutValueThrowsException(): void
    {
        $this->expectExceptionObject(LanguageException::unsupportedValue(\gettype(null), LanguageRule::class));
        $channelContext = static::createStub(ChannelContext::class);
        $scope = new CheckoutRuleScope($channelContext);
        $rule = new LanguageRule(Rule::OPERATOR_EQ, null);
        $rule->match($scope);
    }

    private function validateConstraint(string $field, mixed $value): ConstraintViolationListInterface
    {
        return Validation::createValidator()->validate($value, (new LanguageRule())->getConstraints()[$field]);
    }

    private function assertViolationCode(ConstraintViolationListInterface $violations, string $expectedCode, int $expectedCount = 1): void
    {
        static::assertCount($expectedCount, $violations);

        foreach ($violations as $violation) {
            static::assertSame($expectedCode, $violation->getCode());
        }
    }
}
