<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\User\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\CheckoutRuleScope;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Rule\UserCustomFieldRule;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Rule\Rule;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
#[CoversClass(UserCustomFieldRule::class)]
#[Group('rules')]
class UserCustomFieldRuleTest extends TestCase
{
    private const CUSTOM_FIELD_NAME = 'custom_test';

    private Stub $user;

    private CheckoutRuleScope $scope;

    protected function setUp(): void
    {
        $channelContext = static::createStub(ChannelContext::class);
        $channelContext->method('getContext')->willReturn(Context::createDefaultContext());

        $this->user = static::createStub(UserEntity::class);
        $channelContext->method('getUser')->willReturn($this->user);

        $this->scope = new CheckoutRuleScope($channelContext);
    }

    public function testGetName(): void
    {
        $rule = new UserCustomFieldRule();
        static::assertSame('userCustomField', $rule->getName());
    }

    public function testGetConstraints(): void
    {
        $rule = new UserCustomFieldRule();
        $ruleConstraints = $rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
        static::assertArrayHasKey('renderedField', $ruleConstraints, 'Rule Constraint renderedField is not defined');
        static::assertArrayHasKey('renderedFieldValue', $ruleConstraints, 'Rule Constraint renderedFieldValue is not defined');
        static::assertArrayHasKey('selectedField', $ruleConstraints, 'Rule Constraint selectedField is not defined');
        static::assertArrayHasKey('selectedFieldSet', $ruleConstraints, 'Rule Constraint selectedFieldSet is not defined');
    }

    public function testBooleanCustomFieldFalseWithNoValue(): void
    {
        $rule = self::setupBoolRule(false);
        $this->setUserCustomFields([]);
        static::assertTrue($rule->match($this->scope));
    }

    public function testMatchWithWrongRuleScope(): void
    {
        $scope = static::createStub(TestRuleScope::class);

        $rule = new UserCustomFieldRule();
        $match = $rule->match($scope);

        static::assertFalse($match);
    }

    public function testMatchWithoutUser(): void
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getUser')->willReturn(null);

        $scope = new CheckoutRuleScope($context);

        $rule = self::setupBoolRule(true);

        static::assertFalse($rule->match($scope));
    }

    #[DataProvider('getStringRuleValueWhichShouldBeConsideredAsTrueProvider')]
    public function testBooleanCustomFieldTrueWhenIsRuleIsSetupAsString(string $value): void
    {
        $rule = self::setupBoolRule($value);
        $this->setUserCustomFields([
            self::CUSTOM_FIELD_NAME => true,
        ]);
        static::assertTrue($rule->match($this->scope));
    }

    #[DataProvider('getStringRuleValueWhichShouldBeConsideredAsFalseProvider')]
    public function testBooleanCustomFieldFalseWhenIsRuleIsSetupAsString(string $value): void
    {
        $rule = self::setupBoolRule($value);
        $this->setUserCustomFields([
            self::CUSTOM_FIELD_NAME => false,
        ]);
        static::assertTrue($rule->match($this->scope));
    }

    #[DataProvider('getStringRuleValueWhichShouldBeConsideredAsTrueProvider')]
    public function testBooleanCustomFieldInvalidAsString(string $value): void
    {
        $rule = self::setupBoolRule($value);
        $this->setUserCustomFields([
            self::CUSTOM_FIELD_NAME => false,
        ]);
        static::assertFalse($rule->match($this->scope));
    }

    public function testTextCustomFieldUnequalOperator(): void
    {
        // Case: the rule checks for some text but the line item custom field value is null
        // 'testValue' != null -> true
        $rule = new UserCustomFieldRule();
        $rule->assign(
            [
                'operator' => Rule::OPERATOR_NEQ,
                'renderedField' => [
                    'type' => 'text',
                    'name' => self::CUSTOM_FIELD_NAME,
                ],
                'renderedFieldValue' => 'testValue',
            ]
        );
        $this->setUserCustomFields([self::CUSTOM_FIELD_NAME => null]);
        static::assertTrue($rule->match($this->scope));
    }

    /**
     * @param array<int>|bool|string|null $customFieldValueInUser
     */
    #[DataProvider('customFieldCheckoutScopeProvider')]
    public function testCustomFieldCheckoutScope(
        UserCustomFieldRule $rule,
        array|bool|string|null $customFieldValueInUser,
        bool $result
    ): void {
        $this->setUserCustomFields([self::CUSTOM_FIELD_NAME => $customFieldValueInUser]);
        static::assertSame($result, $rule->match($this->scope));
    }

    /**
     * @return iterable<string, array<string, array<int, int>|UserCustomFieldRule|string|bool>>
     */
    public static function customFieldCheckoutScopeProvider(): iterable
    {
        yield 'testBooleanCustomFieldTrue' => [
            'rule' => self::setupBoolRule(true),
            'customFieldValueInUser' => true,
            'result' => true,
        ];
        yield 'testBooleanCustomFieldFalse' => [
            'rule' => self::setupBoolRule(false),
            'customFieldValueInUser' => false,
            'result' => true,
        ];
        yield 'testBooleanCustomFieldNull' => [
            'rule' => self::setupBoolRule(null),
            'customFieldValueInUser' => false,
            'result' => true,
        ];
        yield 'testBooleanCustomFieldInvalid' => [
            'rule' => self::setupBoolRule(false),
            'customFieldValueInUser' => true,
            'result' => false,
        ];
        yield 'matching string custom field satisfies the rule' => [
            'rule' => self::setupStringRule('my_test_value'),
            'customFieldValueInUser' => 'my_test_value',
            'result' => true,
        ];
        yield 'different string custom field does not satisfy the rule' => [
            'rule' => self::setupStringRule('my_test_value'),
            'customFieldValueInUser' => 'my_invalid_value',
            'result' => false,
        ];
        yield 'overlapping multi select custom field satisfies the rule' => [
            'rule' => self::setupSelectRule([1, 2], ['componentName' => 'sw-multi-select']),
            'customFieldValueInUser' => [1],
            'result' => true,
        ];
        yield 'different multi select custom field does not satisfy the rule' => [
            'rule' => self::setupSelectRule([1, 2], ['componentName' => 'sw-multi-select']),
            'customFieldValueInUser' => [3],
            'result' => false,
        ];
        yield 'null multi select rule value does not satisfy the rule' => [
            'rule' => self::setupSelectRule(null, ['componentName' => 'sw-multi-select']),
            'customFieldValueInUser' => [3],
            'result' => false,
        ];
    }

    /**
     * @return iterable<array<string>>
     */
    public static function getStringRuleValueWhichShouldBeConsideredAsTrueProvider(): iterable
    {
        yield 'yes string is treated as true' => ['yes'];
        yield 'uppercase true string is treated as true' => ['True'];
        yield 'one string is treated as true' => ['1'];
        yield 'lowercase true string is treated as true' => ['true'];
        yield 'yes string with trailing space is treated as true' => ['yes '];
    }

    /**
     * @return iterable<array<string>>
     */
    public static function getStringRuleValueWhichShouldBeConsideredAsFalseProvider(): iterable
    {
        yield 'no string is treated as false' => ['no'];
        yield 'uppercase false string is treated as false' => ['False'];
        yield 'zero string is treated as false' => ['0'];
        yield 'lowercase false string is treated as false' => ['false'];
        yield 'no string with trailing space is treated as false' => ['no '];
        yield 'unknown string is treated as false' => ['some string'];
    }

    /**
     * @param array<string, mixed> $customFields
     */
    private function setUserCustomFields(array $customFields = []): void
    {
        $this->user->method('getCustomFields')->willReturn($customFields);
    }

    /**
     * @param array<int>|bool|string|null $customFieldValue
     */
    private static function setupBoolRule(array|bool|string|null $customFieldValue): UserCustomFieldRule
    {
        $rule = new UserCustomFieldRule();
        $rule->assign(
            [
                'operator' => Rule::OPERATOR_EQ,
                'renderedField' => [
                    'type' => 'bool',
                    'name' => self::CUSTOM_FIELD_NAME,
                ],
                'renderedFieldValue' => $customFieldValue,
            ]
        );

        return $rule;
    }

    /**
     * @param array<int>|bool|string|null $customFieldValue
     */
    private static function setupStringRule(array|bool|string|null $customFieldValue): UserCustomFieldRule
    {
        $rule = new UserCustomFieldRule();

        $rule->assign(
            [
                'operator' => Rule::OPERATOR_EQ,
                'renderedField' => [
                    'type' => 'string',
                    'name' => self::CUSTOM_FIELD_NAME,
                ],
                'renderedFieldValue' => $customFieldValue,
            ]
        );

        return $rule;
    }

    /**
     * @param array<int>|bool|string|null $customFieldValue
     * @param array<string, string> $config
     */
    private static function setupSelectRule(array|bool|string|null $customFieldValue, array $config = []): UserCustomFieldRule
    {
        $rule = new UserCustomFieldRule();
        $rule->assign(
            [
                'operator' => Rule::OPERATOR_EQ,
                'renderedField' => [
                    'type' => 'select',
                    'name' => self::CUSTOM_FIELD_NAME,
                    'config' => $config,
                ],
                'renderedFieldValue' => $customFieldValue,
            ]
        );

        return $rule;
    }
}
