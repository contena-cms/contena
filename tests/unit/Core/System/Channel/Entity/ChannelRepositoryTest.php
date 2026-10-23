<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\System\Channel\Entity;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Field\FkField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\IdField;
use Contena\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Contena\Core\Framework\DataAbstractionLayer\FieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInterface;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelException;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(ChannelRepository::class)]
class ChannelRepositoryTest extends TestCase
{
    public function testSearchIdsIsRoutedThroughTheInstrumentor(): void
    {
        $operations = [];
        $repo = $this->repository($operations);

        $repo->searchIds(new Criteria(), $this->channelContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH_IDS], $operations);
    }

    public function testAggregateIsRoutedThroughTheInstrumentor(): void
    {
        $operations = [];
        $repo = $this->repository($operations);

        $repo->aggregate(new Criteria(), $this->channelContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_AGGREGATE], $operations);
    }

    public function testReadOnlySearchIsRoutedThroughTheInstrumentor(): void
    {
        $operations = [];
        $repo = $this->repository($operations);

        // an empty criteria needs no id lookup, so only the outer search operation is measured
        $repo->search(new Criteria(), $this->channelContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH], $operations);
    }

    public function testSearchWithIdLookupDoesNotEmitANestedSearchIds(): void
    {
        $operations = [];
        $repo = $this->repository($operations);

        $criteria = new Criteria();
        $criteria->setTerm('foo');
        $repo->search($criteria, $this->channelContext());

        // the id lookup runs through the private doSearch(); the outer search is the only measured operation,
        // matching EntityRepository so the same query is not double-counted by a nested searchIds sample
        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH], $operations);
    }

    public function testSearchWithAggregationsDoesNotEmitANestedAggregate(): void
    {
        $operations = [];
        $repo = $this->repository($operations);

        $criteria = new Criteria();
        $criteria->addAggregation(new CountAggregation('agg', 'id'));
        $repo->search($criteria, $this->channelContext());

        // the aggregate is a nested sub-operation (profiled, not metered); only the outer search is measured
        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH], $operations);
    }

    #[DataProvider('criteriaDepthProvider')]
    public function testEveryNestedCriteriaIsProcessed(int $depth): void
    {
        // the root criteria plus every nested one, so no definition restriction is silently skipped
        static::assertSame($depth + 1, $this->countProcessedCriteria($depth));
    }

    public function testACriteriaAboveTheLimitIsRejected(): void
    {
        // answering it would mean returning data the remaining criteria never restricted
        $this->expectExceptionObject(ChannelException::tooManyNestedCriteria(100));

        $this->countProcessedCriteria(250);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function criteriaDepthProvider(): iterable
    {
        yield 'a single criteria' => [0];
        yield 'below the limit' => [10];
        yield 'at the limit' => [98];
    }

    /**
     * Builds a criteria nested `$depth` levels deep and returns how many of its criteria the
     * repository handed to the definition.
     */
    private function countProcessedCriteria(int $depth): int
    {
        $criteria = new Criteria();
        $nested = $criteria;
        for ($i = 0; $i < $depth; ++$i) {
            $nested = $nested->getAssociation('children');
        }

        $registry = new StaticDefinitionInstanceRegistry(
            [new SelfReferencingChannelDefinition()],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $dispatcher = new EventDispatcher();
        $processed = 0;
        $dispatcher->addListener(
            \sprintf('channel.%s.process.criteria', SelfReferencingChannelDefinition::ENTITY_NAME),
            function () use (&$processed): void {
                ++$processed;
            }
        );

        /** @var ChannelRepository<EntityCollection<Entity>> $repository */
        $repository = new ChannelRepository(
            $registry->getByEntityName(SelfReferencingChannelDefinition::ENTITY_NAME),
            static::createStub(EntityReaderInterface::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            $dispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $context = static::createStub(ChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $repository->searchIds($criteria, $context);

        return $processed;
    }

    /**
     * @param list<string> $operations
     *
     * @return ChannelRepository<EntityCollection<Entity>>
     */
    private function repository(array &$operations): ChannelRepository
    {
        /** @var ChannelRepository<EntityCollection<Entity>> $repository */
        $repository = new ChannelRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
            $this->recordingInstrumentor($operations),
        );

        return $repository;
    }

    private function channelContext(): ChannelContext
    {
        $context = static::createStub(ChannelContext::class);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        return $context;
    }

    /**
     * An instrumentor stub that records each measured operation and transparently runs the wrapped
     * callback, so tests can assert the repository delegates to it.
     *
     * @param list<string> $operations
     */
    private function recordingInstrumentor(array &$operations): DalSearchInstrumentor
    {
        $instrumentor = static::createStub(DalSearchInstrumentor::class);
        $instrumentor->method('measure')->willReturnCallback(
            function (string $operation, EntityDefinition $definition, Criteria $criteria, \Closure $callback) use (&$operations): mixed {
                $operations[] = $operation;

                return $callback();
            }
        );

        return $instrumentor;
    }
}

/**
 * An entity that associates itself, so the test can build a criteria of arbitrary depth.
 *
 * @internal
 */
class SelfReferencingChannelDefinition extends EntityDefinition implements ChannelDefinitionInterface
{
    public const ENTITY_NAME = 'self_referencing_test';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function processCriteria(Criteria $criteria, ChannelContext $context): void
    {
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new FkField('parent_id', 'parentId', self::class),
            new OneToManyAssociationField('children', self::class, 'parent_id'),
        ]);
    }
}
