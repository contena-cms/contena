<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Rule\DataAbstractionLayer\RuleAreaUpdater;
use Contena\Core\Content\Rule\RuleDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(RuleAreaUpdater::class)]
class RuleAreaUpdaterTest extends TestCase
{
    private Connection&Stub $connection;

    private RuleDefinition $definition;

    private Stub&RuleConditionRegistry $conditionRegistry;

    private RuleAreaUpdater $areaUpdater;

    private StaticDefinitionInstanceRegistry $registry;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());

        $this->conditionRegistry = static::createStub(RuleConditionRegistry::class);

        $registry = new StaticDefinitionInstanceRegistry(
            [
                RuleAreaDefinitionTest::class,
                RuleAreaTestManyToMany::class,
                RuleAreaTestOneToMany::class,
                RuleAreaTestOneToOne::class,
                RuleAreaTestManyToOne::class,
                ReferenceDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        /** @var RuleDefinition $entityDefinition */
        $entityDefinition = $registry->getByEntityName('rule');
        $this->definition = $entityDefinition;
        $this->registry = $registry;

        $this->clock = new MockClock('2026-01-13 11:00:00');
        $this->areaUpdater = $this->createAreaUpdater();
    }

    public function testUpdate(): void
    {
        $id = Uuid::randomHex();

        $resultStatement = static::createStub(Result::class);
        $resultStatement->method('fetchAllAssociative')->willReturn([
            [
                'array_key' => $id,
                'oneToOne' => '1',
                'oneToMany' => '1',
                'manyToOne' => '1',
                'manyToMany' => '1',
            ],
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects($this->once())
            ->method('executeQuery')
            ->willReturnCallback(function (string $sql, array $params) use ($resultStatement, $id): Result {
                static::assertSame(['ids' => Uuid::fromHexToBytesList([$id]), 'flowTypes' => ['flowRule']], $params);

                return $resultStatement;
            });

        $statement = $this->createMock(Statement::class);
        $params = [
            ['areas', json_encode(['area-a', 'area-b', 'area-c', 'area-d'])],
            ['id', Uuid::fromHexToBytes($id)],
            ['updatedAt', $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
        ];
        $matcher = $this->exactly(\count($params));
        $statement->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(static function (string $key, $value) use ($matcher, $params): void {
                self::assertSame($params[$matcher->numberOfInvocations() - 1][0], $key);
                self::assertSame($params[$matcher->numberOfInvocations() - 1][1], $value);
            });
        $statement->expects($this->once())->method('executeStatement')->willReturn(1);
        $connection->method('prepare')->willReturn($statement);

        $this->conditionRegistry->method('getFlowRuleNames')->willReturn(['flowRule']);

        $this->createAreaUpdater($connection)->update([$id]);
    }

    public function testTriggerChangeset(): void
    {
        $fieldCollection = $this->definition->getFields();

        $oneToManyField = $fieldCollection->get('oneToMany');
        $manyToOneField = $fieldCollection->get('manyToOne');
        $manyToManyField = $fieldCollection->get('manyToMany');

        static::assertInstanceOf(OneToManyAssociationField::class, $oneToManyField);
        static::assertInstanceOf(ManyToOneAssociationField::class, $manyToOneField);
        static::assertInstanceOf(ManyToManyAssociationField::class, $manyToManyField);

        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [
            new DeleteCommand($oneToManyField->getReferenceDefinition(), [], static::createStub(EntityExistence::class)),
            new UpdateCommand($manyToOneField->getReferenceDefinition(), [], [], static::createStub(EntityExistence::class), ''),
            new UpdateCommand($oneToManyField->getReferenceDefinition(), ['rule_id' => 'foo'], [], static::createStub(EntityExistence::class), ''),
            new UpdateCommand($manyToManyField->getReferenceDefinition(), ['rule_id' => 'foo'], [], static::createStub(EntityExistence::class), ''),
        ]);

        $this->areaUpdater->triggerChangeSet($event);

        /** @var DeleteCommand[]|UpdateCommand[] $commands */
        $commands = $event->getCommands();

        static::assertCount(4, $commands);
        static::assertTrue($commands[0]->requiresChangeSet());
        static::assertFalse($commands[1]->requiresChangeSet());
        static::assertTrue($commands[2]->requiresChangeSet());
        static::assertTrue($commands[3]->requiresChangeSet());
    }

    public function testOnEntityWritten(): void
    {
        $context = Context::createDefaultContext();

        $idA = Uuid::randomHex();
        $idB = Uuid::randomBytes();
        $idC = Uuid::randomBytes();
        $idD = Uuid::randomBytes();
        $idE = Uuid::randomBytes();

        $event = new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent('many_to_one', [
                new EntityWriteResult($idA, [], 'many_to_one', EntityWriteResult::OPERATION_INSERT),
            ], $context, []),
            new EntityWrittenEvent('one_to_many', [
                new EntityWriteResult($idA, ['ruleId' => $idA], 'one_to_many', EntityWriteResult::OPERATION_INSERT),
                new EntityWriteResult($idA, [], 'one_to_many', EntityWriteResult::OPERATION_UPDATE, null, new ChangeSet(
                    ['rule_id' => $idB],
                    ['rule_id' => $idC],
                    false
                )),
                new EntityWriteResult($idA, [], 'one_to_many', EntityWriteResult::OPERATION_DELETE, null, new ChangeSet(
                    ['rule_id' => $idD],
                    ['rule_id' => null],
                    true
                )),
            ], $context, []),
            new EntityWrittenEvent('mapping', [
                new EntityWriteResult(
                    $idA,
                    [
                        'ruleId' => Uuid::fromBytesToHex($idE),
                        'referenceId' => Uuid::randomHex(),
                    ],
                    'mapping',
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], $context, []),
        ]), []);

        $resultStatement = $this->createMock(Result::class);
        $resultStatement->expects($this->once())->method('fetchAllAssociative')->willReturn([]);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects($this->once())
            ->method('executeQuery')
            ->willReturnCallback(function (string $sql, array $params) use ($resultStatement, $idA, $idB, $idC, $idD, $idE): Result {
                static::assertSame(['ids' => [Uuid::fromHexToBytes($idA), $idB, $idC, $idD, $idE], 'flowTypes' => ['flowRule']], $params);

                return $resultStatement;
            });

        $statement = static::createStub(Statement::class);
        $statement->method('getWrappedStatement')->willReturn(static::createStub(\Doctrine\DBAL\Driver\Statement::class));
        $connection->method('prepare')->willReturn($statement);

        $this->conditionRegistry->method('getFlowRuleNames')->willReturn(['flowRule']);

        $this->createAreaUpdater($connection)->onEntityWritten($event);
    }

    private function createAreaUpdater(?Connection $connection = null): RuleAreaUpdater
    {
        return new RuleAreaUpdater(
            $connection ?? $this->connection,
            $this->definition,
            $this->conditionRegistry,
            static::createStub(CacheInvalidator::class),
            $this->registry,
            $this->clock,
        );
    }
}

/**
 * @internal
 */
class RuleAreaDefinitionTest extends RuleDefinition
{
    public function getEntityName(): string
    {
        return 'rule';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new OneToOneAssociationField('oneToOne', 'one_to_one', 'id', RuleAreaTestOneToOne::class)->addFlags(new RuleAreas('area-a')),
            new OneToManyAssociationField('oneToMany', RuleAreaTestOneToMany::class, 'rule_id')->addFlags(new RuleAreas('area-b')),
            new ManyToOneAssociationField('manyToOne', 'many_to_one', RuleAreaTestManyToOne::class)->addFlags(new RuleAreas('area-c')),
            new ManyToManyAssociationField('manyToMany', RuleAreaDefinitionTest::class, RuleAreaTestManyToMany::class, 'rule_id', 'reference_id')->addFlags(new RuleAreas('area-d')),
            new FkField('rule_id', 'ruleId', RuleAreaDefinitionTest::class),
        ]);
    }
}

/**
 * @internal
 */
class RuleAreaTestOneToOne extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'one_to_one';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([new IdField('id', 'id')]);
    }
}

/**
 * @internal
 */
class RuleAreaTestOneToMany extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'one_to_many';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
            new FkField('rule_id', 'ruleId', RuleAreaDefinitionTest::class),
        ]);
    }
}

/**
 * @internal
 */
class RuleAreaTestManyToOne extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'many_to_one';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([new IdField('id', 'id')]);
    }
}

/**
 * @internal
 */
class RuleAreaTestManyToMany extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'mapping';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new FkField('rule_id', 'ruleId', RuleAreaDefinitionTest::class),
            new FkField('reference_id', 'referenceId', ReferenceDefinition::class),
        ]);
    }
}

/**
 * @internal
 */
class ReferenceDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'reference';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new ManyToManyAssociationField('rule', RuleAreaDefinitionTest::class, RuleAreaTestManyToMany::class, 'reference_id', 'rule_id'),
        ]);
    }
}
