<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Field\DataScopeField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\VersionManager;
use Contena\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Contena\Core\Framework\Test\DataAbstractionLayer\Field\DataAbstractionLayerFieldTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
class DataScopeReadBoundaryTest extends TestCase
{
    use DataAbstractionLayerFieldTestBehaviour {
        tearDown as protected tearDownDefinitions;
    }
    use KernelTestBehaviour;

    private Connection $connection;

    private EntityDefinition $definition;

    /**
     * @var EntityRepository<EntityCollection<Entity>>
     */
    private EntityRepository $repository;

    private EntityReaderInterface $reader;

    private EntitySearcherInterface $searcher;

    private EntityAggregatorInterface $aggregator;

    protected function setUp(): void
    {
        $this->definition = $this->registerDefinition(DataScopeReadBoundaryDefinition::class);
        $this->connection = static::getContainer()->get(Connection::class);
        $this->reader = static::getContainer()->get(EntityReaderInterface::class);
        $this->searcher = static::getContainer()->get(EntitySearcherInterface::class);
        $this->aggregator = static::getContainer()->get(EntityAggregatorInterface::class);
        $this->repository = new EntityRepository(
            $this->definition,
            $this->reader,
            static::getContainer()->get(VersionManager::class),
            $this->searcher,
            $this->aggregator,
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(EntityLoadedEventFactory::class),
        );

        $this->connection->executeStatement(<<<'SQL'
DROP TABLE IF EXISTS `data_scope_read_boundary_test`;
CREATE TABLE `data_scope_read_boundary_test` (
    `id` BINARY(16) NOT NULL,
    `data_scope_id` BINARY(16) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `created_at` DATETIME(3) NOT NULL,
    `updated_at` DATETIME(3) NULL,
    PRIMARY KEY (`id`),
    INDEX `idx.data_scope` (`data_scope_id`)
);
SQL);
        $this->connection->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->tearDownDefinitions();
        $this->connection->rollBack();
        $this->connection->executeStatement('DROP TABLE `data_scope_read_boundary_test`');
    }

    public function testDirectReaderEnforcesTheContextDataScope(): void
    {
        $scopeA = Uuid::randomHex();
        $scopeB = Uuid::randomHex();
        $id = $this->createRecord('reader', Context::createTenantContext($scopeA));
        $criteria = new Criteria([$id]);

        static::assertCount(1, $this->reader->read($this->definition, clone $criteria, Context::createTenantContext($scopeA)));
        static::assertCount(0, $this->reader->read($this->definition, clone $criteria, Context::createTenantContext($scopeB)));
        static::assertCount(0, $this->reader->read($this->definition, clone $criteria, Context::createDefaultContext()));
        static::assertCount(1, $this->reader->read($this->definition, clone $criteria, Context::createGlobalContext()));
    }

    public function testDirectSearcherEnforcesTheContextDataScope(): void
    {
        $scopeA = Uuid::randomHex();
        $scopeB = Uuid::randomHex();
        $id = $this->createRecord('searcher', Context::createTenantContext($scopeA));
        $criteria = new Criteria([$id]);

        static::assertSame([$id], $this->searcher->search($this->definition, clone $criteria, Context::createTenantContext($scopeA))->getIds());
        static::assertSame([], $this->searcher->search($this->definition, clone $criteria, Context::createTenantContext($scopeB))->getIds());
        static::assertSame([], $this->searcher->search($this->definition, clone $criteria, Context::createDefaultContext())->getIds());
        static::assertSame([$id], $this->searcher->search($this->definition, clone $criteria, Context::createGlobalContext())->getIds());
    }

    public function testDirectAggregatorEnforcesTheContextDataScope(): void
    {
        $scopeA = Uuid::randomHex();
        $scopeB = Uuid::randomHex();
        $id = $this->createRecord('aggregator', Context::createTenantContext($scopeA));

        static::assertSame(1, $this->aggregateCount($id, Context::createTenantContext($scopeA)));
        static::assertSame(0, $this->aggregateCount($id, Context::createTenantContext($scopeB)));
        static::assertSame(0, $this->aggregateCount($id, Context::createDefaultContext()));
        static::assertSame(1, $this->aggregateCount($id, Context::createGlobalContext()));
    }

    public function testCloneCanNotReadASourceFromAnotherDataScope(): void
    {
        $scopeA = Uuid::randomHex();
        $scopeB = Uuid::randomHex();
        $sourceId = $this->createRecord('source', Context::createTenantContext($scopeA));

        $this->expectExceptionObject(DataAbstractionLayerException::cannotCreateNewVersion(
            DataScopeReadBoundaryDefinition::ENTITY_NAME,
            $sourceId,
        ));

        $this->repository->clone(
            $sourceId,
            Context::createTenantContext($scopeB),
            Uuid::randomHex(),
            new CloneBehavior([
                'dataScopeId' => $scopeB,
                'name' => 'cross-scope clone',
            ]),
        );
    }

    public function testCloneCanReadASourceFromItsOwnDataScope(): void
    {
        $scopeId = Uuid::randomHex();
        $context = Context::createTenantContext($scopeId);
        $sourceId = $this->createRecord('source', $context);
        $cloneId = Uuid::randomHex();

        $this->repository->clone(
            $sourceId,
            $context,
            $cloneId,
            new CloneBehavior(['name' => 'same-scope clone']),
        );

        static::assertTrue($this->repository->search(new Criteria([$cloneId]), $context)->getEntities()->has($cloneId));
        static::assertFalse($this->repository->search(new Criteria([$cloneId]), Context::createDefaultContext())->getEntities()->has($cloneId));
    }

    private function createRecord(string $name, Context $context): string
    {
        $id = Uuid::randomHex();
        $this->repository->create([[
            'id' => $id,
            'name' => $name,
        ]], $context);

        return $id;
    }

    private function aggregateCount(string $id, Context $context): int
    {
        $criteria = new Criteria([$id]);
        $criteria->addAggregation(new CountAggregation('count', 'id'));

        $result = $this->aggregator->aggregate($this->definition, $criteria, $context)->get('count');
        static::assertInstanceOf(CountResult::class, $result);

        return $result->getCount();
    }
}

/**
 * @internal
 */
class DataScopeReadBoundaryDefinition extends EntityDefinition
{
    final public const string ENTITY_NAME = 'data_scope_read_boundary_test';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new DataScopeField(),
            new IdField('id', 'id')->addFlags(new PrimaryKey(), new Required()),
            new StringField('name', 'name')->addFlags(new Required()),
        ]);
    }
}
