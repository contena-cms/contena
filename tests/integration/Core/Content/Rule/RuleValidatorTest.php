<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Rule;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Checkout\Cart\Rule\AlwaysValidRule;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionCollection;
use Contena\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionEntity;
use Contena\Core\Content\Rule\RuleCollection;
use Contena\Core\Content\Rule\RuleEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\User\Rule\UserGroupRule;

/**
 * @internal
 */
class RuleValidatorTest extends TestCase
{
    use IntegrationTestBehaviour;

    private Context $context;

    /**
     * @var EntityRepository<RuleConditionCollection>
     */
    private EntityRepository $ruleConditionRepository;

    /**
     * @var EntityRepository<RuleCollection>
     */
    private EntityRepository $ruleRepository;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->ruleRepository = static::getContainer()->get('rule.repository');
        $this->ruleConditionRepository = static::getContainer()->get('rule_condition.repository');
    }

    /**
     * @param array<array<string, string|array<string, mixed>>> $conditions
     */
    #[DataProvider('providerRuleCases')]
    public function testItCanCreateRulesOnValidInput(string $conditionId, array $conditions): void
    {
        $ruleId = Uuid::randomHex();

        $this->ruleRepository->create([
            [
                'id' => $ruleId,
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => $conditions,
            ],
        ], $this->context);

        $criteria = new Criteria([$ruleId]);
        $criteria->addAssociation('conditions');

        /** @var RuleEntity $rule */
        $rule = $this->ruleRepository->search($criteria, $this->context)->getEntities()->get($ruleId);

        /** @var RuleConditionCollection $ruleConditions */
        $ruleConditions = $rule->getConditions();
        static::assertCount(1, $ruleConditions);
        static::assertNotNull($ruleConditions->get($conditionId));
    }

    public function testItThrowsIfTypeIsMissing(): void
    {
        $ruleData = [
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'type' => 'userOrderCount',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                    ], [
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->ruleRepository->create($ruleData, $this->context);
        } catch (WriteException $we) {
            $violations = iterator_to_array($we->getErrors());

            static::assertCount(1, $violations);
            static::assertSame('/0/conditions/1/type', $violations[0]['source']['pointer']);
        }
    }

    public function testWithChildren(): void
    {
        $ruleData = [
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'type' => 'userOrderCount',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                        'children' => [
                            [
                                'value' => [
                                    'operator' => '=',
                                    'count' => 6,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->ruleRepository->create($ruleData, $this->context);
        } catch (WriteException $we) {
            $violations = iterator_to_array($we->getErrors());

            static::assertCount(1, $violations);
            static::assertSame('/0/conditions/0/children/0/type', $violations[0]['source']['pointer']);
        }
    }

    public function testItThrowsIfTypeIsInvalid(): void
    {
        $ruleData = [
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'type' => 'someTypeThatIsHopefullyNotRegistered',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->ruleRepository->create($ruleData, $this->context);
        } catch (WriteException $we) {
            $violations = iterator_to_array($we->getErrors());

            static::assertCount(1, $violations);
            static::assertSame('/0/conditions/0/type', $violations[0]['source']['pointer']);
        }
    }

    public function testItThrowsIfValueIsMissing(): void
    {
        $ruleData = [
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'type' => 'userOrderCount',
                    ],
                ],
            ],
        ];

        try {
            $this->ruleRepository->create($ruleData, $this->context);
        } catch (WriteException $we) {
            $violations = iterator_to_array($we->getErrors());

            static::assertCount(2, $violations);
            static::assertSame('/0/conditions/0/value/count', $violations[0]['source']['pointer']);
            static::assertSame('/0/conditions/0/value/operator', $violations[1]['source']['pointer']);
        }
    }

    public function testItThrowsIfValueContainsInvalidField(): void
    {
        $ruleData = [
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'type' => 'userOrderCount',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                            'thisFieldIsNotValid' => true,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $this->ruleRepository->create($ruleData, $this->context);
        } catch (WriteException $we) {
            $violations = iterator_to_array($we->getErrors());

            static::assertCount(1, $violations);
            static::assertSame('/0/conditions/0/value/thisFieldIsNotValid', $violations[0]['source']['pointer']);
        }
    }

    public function testItCanUpdateValueOnly(): void
    {
        $userOderCountId = Uuid::randomHex();

        $this->ruleRepository->create([
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'id' => $userOderCountId,
                        'type' => 'userOrderCount',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                    ],
                ],
            ],
        ], $this->context);

        $newValue = [
            'count' => 12,
            'operator' => '=',
        ];

        $this->ruleConditionRepository->update([
            [
                'id' => $userOderCountId,
                'value' => $newValue,
            ],
        ], $this->context);

        /** @var RuleConditionEntity $updatedCondition */
        $updatedCondition = $this->ruleConditionRepository->search(new Criteria([$userOderCountId]), $this->context)
            ->getEntities()->get($userOderCountId);

        static::assertSame('userOrderCount', $updatedCondition->getType());
        static::assertSame($newValue, $updatedCondition->getValue());
    }

    public function testItThrowsIfNewTypeMismatchesValue(): void
    {
        $userOrderCountId = Uuid::randomHex();

        $this->ruleRepository->create([
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'id' => $userOrderCountId,
                        'type' => 'userOrderCount',
                        'value' => [
                            'operator' => '=',
                            'count' => 6,
                        ],
                    ],
                ],
            ],
        ], $this->context);

        try {
            $this->ruleConditionRepository->update([
                [
                    'id' => $userOrderCountId,
                    'type' => 'orContainer',
                ],
            ], $this->context);
        } catch (WriteException $we) {
            $violations = iterator_to_array($we->getErrors());
            $pointer = array_column(array_column($violations, 'source'), 'pointer');

            static::assertCount(2, $pointer);
            static::assertContains('/0/value/count', $pointer);
            static::assertContains('/0/value/operator', $pointer);
        }
    }

    public function testItCanClearValueWhenConditionTypeChanges(): void
    {
        $userGroupConditionId = Uuid::randomHex();

        $this->ruleRepository->create([
            [
                'name' => 'super rule',
                'priority' => 15,
                'conditions' => [
                    [
                        'id' => $userGroupConditionId,
                        'type' => UserGroupRule::RULE_NAME,
                        'value' => [
                            'userGroupIds' => [Uuid::randomHex()],
                            'operator' => UserGroupRule::OPERATOR_EQ,
                        ],
                    ],
                ],
            ],
        ], $this->context);

        $this->ruleConditionRepository->update([
            [
                'id' => $userGroupConditionId,
                'type' => AlwaysValidRule::RULE_NAME,
                'value' => null,
            ],
        ], $this->context);

        $updatedCondition = $this->ruleConditionRepository->search(new Criteria([$userGroupConditionId]), $this->context)
            ->getEntities()->get($userGroupConditionId);

        static::assertInstanceOf(RuleConditionEntity::class, $updatedCondition);
        static::assertSame(AlwaysValidRule::RULE_NAME, $updatedCondition->getType());
        static::assertNull($updatedCondition->getValue());
    }

    /**
     * @return iterable<string, array{string, array<array<string, string|array<string, mixed>>>}>
     */
    public static function providerRuleCases(): iterable
    {
        $conditionId = Uuid::randomHex();
        yield 'userOrderCount rule' => [$conditionId, [
            [
                'id' => $conditionId,
                'type' => 'userOrderCount',
                'value' => [
                    'operator' => '=',
                    'count' => 6,
                ],
            ],
        ]];

        yield 'orderCustomField rule' => [$conditionId, [
            [
                'id' => $conditionId,
                'type' => 'orderCustomField',
                'value' => [
                    'operator' => '=',
                    'selectedField' => Uuid::randomHex(),
                    'selectedFieldSet' => Uuid::randomHex(),
                    'renderedFieldValue' => 'string',
                    'renderedField' => [
                        'type' => 'text',
                    ],
                ],
            ],
        ]];

        yield 'userCustomField rule' => [$conditionId, [
            [
                'id' => $conditionId,
                'type' => 'orderCustomField',
                'value' => [
                    'operator' => '=',
                    'selectedField' => Uuid::randomHex(),
                    'selectedFieldSet' => Uuid::randomHex(),
                    'renderedFieldValue' => 'string',
                    'renderedField' => [
                        'type' => 'text',
                    ],
                ],
            ],
        ]];
    }
}
