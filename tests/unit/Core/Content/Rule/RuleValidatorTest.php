<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Contena\Core\Content\Rule\RuleValidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\Rule\UserGroupRule;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(RuleValidator::class)]
class RuleValidatorTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [PreWriteValidationEvent::class => 'preValidate'],
            RuleValidator::getSubscribedEvents()
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $expectedViolationPointers
     */
    #[DataProvider('updateConditionValueProvider')]
    public function testItValidatesUpdateConditionValues(array $payload, array $expectedViolationPointers): void
    {
        $context = Context::createDefaultContext();
        $conditionId = Uuid::randomHex();
        $conditionIdBytes = Uuid::fromHexToBytes($conditionId);
        $definition = $this->getRuleConditionDefinition();
        $storedCondition = $this->createUserGroupCondition($conditionId);

        $searchResult = new EntitySearchResult(
            RuleConditionDefinition::ENTITY_NAME,
            1,
            new RuleConditionCollection([$storedCondition]),
            null,
            new Criteria([$conditionId]),
            $context
        );

        $ruleConditionRepository = $this->createMock(EntityRepository::class);
        $ruleConditionRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($searchResult);

        $validator = new RuleValidator(
            Validation::createValidator(),
            new RuleConditionRegistry([new AlwaysValidRule(), new UserGroupRule()]),
            $ruleConditionRepository,
            static::createStub(EntityRepository::class)
        );

        $event = new PreWriteValidationEvent(
            WriteContext::createFromContext($context),
            [
                new UpdateCommand(
                    $definition,
                    $payload,
                    ['id' => $conditionIdBytes],
                    EntityExistence::createForEntity(
                        RuleConditionDefinition::ENTITY_NAME,
                        ['id' => $conditionIdBytes]
                    ),
                    '/0'
                ),
            ]
        );

        $validator->preValidate($event);

        $violations = iterator_to_array($event->getExceptions()->getErrors());
        $violationPointers = array_column(array_column($violations, 'source'), 'pointer');

        static::assertSame($expectedViolationPointers, $violationPointers);
    }

    /**
     * @return iterable<string, array{payload: array<string, mixed>, expectedViolationPointers: list<string>}>
     */
    public static function updateConditionValueProvider(): iterable
    {
        yield 'uses explicit null as empty value' => [
            'payload' => [
                'type' => AlwaysValidRule::RULE_NAME,
                'value' => null,
            ],
            'expectedViolationPointers' => [],
        ];

        yield 'uses explicit JSON value' => [
            'payload' => [
                'type' => UserGroupRule::RULE_NAME,
                'value' => json_encode([
                    'userGroupIds' => [Uuid::randomHex()],
                    'operator' => UserGroupRule::OPERATOR_EQ,
                ], \JSON_THROW_ON_ERROR),
            ],
            'expectedViolationPointers' => [],
        ];

        yield 'uses stored value when update payload has no value key' => [
            'payload' => [
                'type' => AlwaysValidRule::RULE_NAME,
            ],
            'expectedViolationPointers' => [
                '/0/value/userGroupIds',
                '/0/value/operator',
            ],
        ];

        yield 'validates explicit null as empty value for required rule fields' => [
            'payload' => [
                'type' => UserGroupRule::RULE_NAME,
                'value' => null,
            ],
            'expectedViolationPointers' => [
                '/0/value/userGroupIds',
                '/0/value/operator',
            ],
        ];
    }

    private function createUserGroupCondition(string $id): RuleConditionEntity
    {
        $condition = new RuleConditionEntity();
        $condition->setId($id);
        $condition->setType(UserGroupRule::RULE_NAME);
        $condition->setValue([
            'userGroupIds' => [Uuid::randomHex()],
            'operator' => UserGroupRule::OPERATOR_EQ,
        ]);

        return $condition;
    }

    private function getRuleConditionDefinition(): RuleConditionDefinition
    {
        $registry = new StaticDefinitionInstanceRegistry(
            [RuleConditionDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $definition = $registry->get(RuleConditionDefinition::class);
        static::assertInstanceOf(RuleConditionDefinition::class, $definition);

        return $definition;
    }
}
