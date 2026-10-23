<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntitySearchedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\PartialEntityLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\FieldVisibility;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Contena\Core\Framework\DataAbstractionLayer\VersionManager;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Event\NestedEventDispatcher;
use Contena\Core\Framework\Struct\ArrayEntity;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(EntityRepository::class)]
class EntityRepositoryTest extends TestCase
{
    public function testSearchWithoutFilterDoesNotSearch(): void
    {
        $eventDispatcher = new EventDispatcher();

        $event = null;
        $eventDispatcher->addListener(EntitySearchedEvent::class, static function ($inner) use (&$event): void {
            $event = $inner;
        });

        $reader = $this->createMock(EntityReaderInterface::class);
        $reader->expects($this->once())->method('read');

        $searcher = $this->createMock(EntitySearcherInterface::class);
        $searcher->expects($this->never())->method('search');

        $aggregator = $this->createMock(EntityAggregatorInterface::class);
        $aggregator->expects($this->never())->method('aggregate');

        $repo = new EntityRepository(
            static::createStub(EntityDefinition::class),
            $reader,
            static::createStub(VersionManager::class),
            $searcher,
            $aggregator,
            $eventDispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $repo->search(new Criteria(), Context::createDefaultContext());

        static::assertInstanceOf(EntitySearchedEvent::class, $event);
    }

    public function testSearchWithAggregation(): void
    {
        $eventDispatcher = new EventDispatcher();

        $searchEvent = null;
        $aggregateEvent = null;
        $eventDispatcher->addListener(EntitySearchedEvent::class, static function ($inner) use (&$searchEvent): void {
            $searchEvent = $inner;
        });
        $eventDispatcher->addListener('blog.aggregation.result.loaded', static function ($inner) use (&$aggregateEvent): void {
            $aggregateEvent = $inner;
        });

        $reader = $this->createMock(EntityReaderInterface::class);
        $reader->expects($this->once())->method('read');

        $searcher = $this->createMock(EntitySearcherInterface::class);
        $searcher->expects($this->never())->method('search');

        $aggregator = $this->createMock(EntityAggregatorInterface::class);
        $aggregator->expects($this->once())->method('aggregate');

        $repo = new EntityRepository(
            new BlogDefinition(),
            $reader,
            static::createStub(VersionManager::class),
            $searcher,
            $aggregator,
            $eventDispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $criteria = new Criteria();
        $criteria->setTitle('foo');
        $criteria->addAggregation(new TermsAggregation('test', 'test'));
        $repo->search($criteria, Context::createDefaultContext());

        static::assertInstanceOf(EntitySearchedEvent::class, $searchEvent);
        static::assertInstanceOf(EntityAggregationResultLoadedEvent::class, $aggregateEvent);
    }

    public function testSearchWithFiltersAndNoResult(): void
    {
        $eventDispatcher = new EventDispatcher();

        $event = null;
        $eventDispatcher->addListener(EntitySearchedEvent::class, static function ($inner) use (&$event): void {
            $event = $inner;
        });

        $reader = $this->createMock(EntityReaderInterface::class);
        $reader->expects($this->never())->method('read');

        $searcher = $this->createMock(EntitySearcherInterface::class);
        $searcher->expects($this->once())->method('search');

        $aggregator = $this->createMock(EntityAggregatorInterface::class);
        $aggregator->expects($this->never())->method('aggregate');

        $repo = new EntityRepository(
            new BlogDefinition(),
            $reader,
            static::createStub(VersionManager::class),
            $searcher,
            $aggregator,
            $eventDispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $criteria = new Criteria();
        $criteria->setTerm('foo');

        $repo->search($criteria, Context::createDefaultContext());

        static::assertInstanceOf(EntitySearchedEvent::class, $event);
    }

    public function testSearchWithFiltersAndResult(): void
    {
        $eventDispatcher = new EventDispatcher();

        $event = null;
        $eventDispatcher->addListener(EntitySearchedEvent::class, static function ($inner) use (&$event): void {
            $event = $inner;
        });

        $reader = $this->createMock(EntityReaderInterface::class);
        $blogEntity = new BlogEntity();
        $blogEntity->assign(['id' => 'test-1', 'name' => 'foo']);
        $blogEntity->setUniqueIdentifier('test-1');

        $blogEntity2 = new BlogEntity();
        $blogEntity2->assign(['id' => 'test-2', 'name' => 'foo']);
        $blogEntity2->setUniqueIdentifier('test-2');

        $blogEntity3 = new BlogEntity();
        $blogEntity3->assign(['id' => 'test-3', 'name' => 'foo']);
        $blogEntity3->setUniqueIdentifier('test-3');

        $blogEntity4 = new BlogEntity();
        $blogEntity4->assign(['id' => 'test-4', 'name' => 'foo']);
        $blogEntity4->setUniqueIdentifier('test-4');

        $reader
            ->expects($this->once())
            ->method('read')
            ->willReturn(new BlogCollection([$blogEntity, $blogEntity2, $blogEntity3, $blogEntity4]));

        $searcher = $this->createMock(EntitySearcherInterface::class);
        $data = [
            'test-1' => ['data' => ['fullText' => 'foo'], 'primaryKey' => 'test-1'],
            'test-2' => ['data' => [], 'primaryKey' => 'test-2'],
            'test-3' => ['data' => [], 'primaryKey' => 'test-3'],
        ];
        $searcher
            ->expects($this->once())
            ->method('search')
            ->willReturn(new IdSearchResult(4, $data, new Criteria(), Context::createDefaultContext()));

        $aggregator = $this->createMock(EntityAggregatorInterface::class);
        $aggregator->expects($this->never())->method('aggregate');

        $repo = new EntityRepository(
            new BlogDefinition(),
            $reader,
            static::createStub(VersionManager::class),
            $searcher,
            $aggregator,
            $eventDispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $criteria = new Criteria();
        $criteria->setTerm('foo');

        $result = $repo->search($criteria, Context::createDefaultContext());

        static::assertInstanceOf(EntitySearchedEvent::class, $event);

        static::assertCount(4, $result->getEntities());
        $blogEntity = $result->getEntities()->first();
        static::assertInstanceOf(BlogEntity::class, $blogEntity);

        static::assertSame('test-1', $blogEntity->getId());
        static::assertSame('foo', $blogEntity->getName());

        $extension = $blogEntity->getExtension('search');
        static::assertInstanceOf(ArrayEntity::class, $extension);
        $search = $extension->jsonSerialize();

        static::assertSame('foo', $search['fullText']);
    }

    public function testSearchPartialEvent(): void
    {
        $blog = new PartialEntity();
        $blog->setUniqueIdentifier('test');
        $blog->internalSetEntityData('blog', new FieldVisibility([]));

        $reader = static::createStub(EntityReaderInterface::class);
        $reader
            ->method('read')
            ->willReturn(new EntityCollection([$blog]));

        $eventDispatcher = new EventDispatcher();
        $event = null;
        $eventDispatcher->addListener(EntityLoadedContainerEvent::class, static function ($inner) use (&$event): void {
            $event = $inner;
        });

        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('blog');

        $repo = new EntityRepository(
            $definition,
            $reader,
            static::createStub(VersionManager::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            $eventDispatcher,
            new EntityLoadedEventFactory(static::createStub(DefinitionInstanceRegistry::class))
        );

        $criteria = new Criteria();
        $criteria->addFields(['foo']);
        $repo->search($criteria, Context::createDefaultContext());

        static::assertInstanceOf(EntityLoadedContainerEvent::class, $event);

        $events = $event->getEvents();
        static::assertInstanceOf(NestedEventCollection::class, $events);
        static::assertCount(1, $events);

        $partialEvent = $events->first();

        static::assertInstanceOf(PartialEntityLoadedEvent::class, $partialEvent);

        $partialCollection = $partialEvent->getEntities();

        static::assertCount(1, $partialCollection);

        $partialEntity = $partialCollection[0];
        static::assertInstanceOf(PartialEntity::class, $partialEntity);
        static::assertSame('test', $partialEntity->getUniqueIdentifier());
    }

    public function testGetDefinition(): void
    {
        $definition = new BlogDefinition();

        $repo = new EntityRepository(
            $definition,
            static::createStub(EntityReaderInterface::class),
            static::createStub(VersionManager::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
        );

        static::assertSame($definition, $repo->getDefinition());
    }

    public function testAggregate(): void
    {
        $eventDispatcher = new EventDispatcher();

        $event = null;
        $eventDispatcher->addListener('blog.aggregation.result.loaded', static function ($inner) use (&$event): void {
            $event = $inner;
        });

        $repo = new EntityRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            static::createStub(VersionManager::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            $eventDispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $repo->aggregate(new Criteria(), Context::createDefaultContext());

        static::assertInstanceOf(EntityAggregationResultLoadedEvent::class, $event);
    }

    public function testSearchIds(): void
    {
        $eventDispatcher = new EventDispatcher();

        $searchedEvent = null;
        $eventDispatcher->addListener(EntitySearchedEvent::class, static function ($inner) use (&$searchedEvent): void {
            $searchedEvent = $inner;
        });

        $resultEvent = null;
        $eventDispatcher->addListener('blog.id.search.result.loaded', static function ($inner) use (&$resultEvent): void {
            $resultEvent = $inner;
        });

        $repo = new EntityRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            static::createStub(VersionManager::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            $eventDispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $repo->searchIds(new Criteria(), Context::createDefaultContext());

        static::assertInstanceOf(EntitySearchedEvent::class, $searchedEvent);
        static::assertInstanceOf(EntityIdSearchResultLoadedEvent::class, $resultEvent);
    }

    public function testCreate(): void
    {
        $eventDispatcher = new EventDispatcher();

        $event = null;
        $eventDispatcher->addListener(EntityWrittenContainerEvent::class, static function ($inner) use (&$event): void {
            $event = $inner;
        });

        $versionManager = $this->createMock(VersionManager::class);
        $versionManager
            ->expects($this->once())
            ->method('insert')
            ->willReturn([[new EntityWriteResult('test', [], 'blog', EntityWriteResult::OPERATION_INSERT)]]);

        $repo = new EntityRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            $versionManager,
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            $eventDispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $repo->create([['name' => 'foo']], Context::createDefaultContext());

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $event);

        static::assertSame(['test'], $event->getPrimaryKeys('blog'));
    }

    public function testUpdate(): void
    {
        $eventDispatcher = new EventDispatcher();

        $event = null;
        $eventDispatcher->addListener(EntityWrittenContainerEvent::class, static function ($inner) use (&$event): void {
            $event = $inner;
        });

        $versionManager = $this->createMock(VersionManager::class);
        $versionManager
            ->expects($this->once())
            ->method('update')
            ->willReturn([[new EntityWriteResult('test', [], 'blog', EntityWriteResult::OPERATION_UPDATE)]]);

        $repo = new EntityRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            $versionManager,
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            $eventDispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $repo->update([['name' => 'foo']], Context::createDefaultContext());

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $event);

        static::assertSame(['test'], $event->getPrimaryKeys('blog'));
    }

    public function testWrittenEventsAreDispatchedInSystemScopeWithOriginalSource(): void
    {
        $dispatcher = new EventDispatcher();
        $eventDispatcher = new NestedEventDispatcher($dispatcher);

        $source = new AdminApiSource('admin_user-id');
        $context = Context::createDefaultContext($source);

        $containerListenerWasCalled = false;
        $dispatcher->addListener(EntityWrittenContainerEvent::class, static function (EntityWrittenContainerEvent $event) use (&$containerListenerWasCalled, $source): void {
            $containerListenerWasCalled = true;

            static::assertSame(Context::SYSTEM_SCOPE, $event->getContext()->getScope());
            static::assertTrue($event->getContext()->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
            static::assertSame($source, $event->getContext()->getSource());
        });

        $nestedListenerWasCalled = false;
        $dispatcher->addListener('blog.written', static function (EntityWrittenEvent $event) use (&$nestedListenerWasCalled, $source): void {
            $nestedListenerWasCalled = true;

            static::assertSame(Context::SYSTEM_SCOPE, $event->getContext()->getScope());
            static::assertTrue($event->getContext()->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
            static::assertSame($source, $event->getContext()->getSource());
        });

        $versionManager = $this->createMock(VersionManager::class);
        $versionManager
            ->expects($this->once())
            ->method('update')
            ->willReturn([[new EntityWriteResult('test', [], 'blog', EntityWriteResult::OPERATION_UPDATE)]]);

        $repo = new EntityRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            $versionManager,
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            $eventDispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $repo->update([['name' => 'foo']], $context);

        static::assertTrue($containerListenerWasCalled);
        static::assertTrue($nestedListenerWasCalled);
        static::assertSame(Context::USER_SCOPE, $context->getScope());
        static::assertFalse($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
    }

    public function testUpsert(): void
    {
        $eventDispatcher = new EventDispatcher();

        $event = null;
        $eventDispatcher->addListener(EntityWrittenContainerEvent::class, static function ($inner) use (&$event): void {
            $event = $inner;
        });

        $versionManager = $this->createMock(VersionManager::class);
        $versionManager
            ->expects($this->once())
            ->method('upsert')
            ->willReturn([[new EntityWriteResult('test', [], 'blog', EntityWriteResult::OPERATION_UPDATE)]]);

        $repo = new EntityRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            $versionManager,
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            $eventDispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $repo->upsert([['name' => 'foo']], Context::createDefaultContext());

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $event);

        static::assertSame(['test'], $event->getPrimaryKeys('blog'));
    }

    public function testDelete(): void
    {
        $eventDispatcher = new EventDispatcher();

        $event = null;
        $eventDispatcher->addListener(EntityWrittenContainerEvent::class, static function ($inner) use (&$event): void {
            $event = $inner;
        });

        $versionManager = $this->createMock(VersionManager::class);
        $writeResult = new WriteResult(
            ['blog' => [new EntityWriteResult('test', [], 'blog', EntityWriteResult::OPERATION_DELETE)]],
            [],
            [
                'blog_translation' => [new EntityWriteResult('foo', [], 'blog_translation', EntityWriteResult::OPERATION_DELETE)],
            ]
        );

        $versionManager
            ->expects($this->once())
            ->method('delete')
            ->willReturn($writeResult);

        $repo = new EntityRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            $versionManager,
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            $eventDispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $repo->delete([['id' => 'test']], Context::createDefaultContext());

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $event);

        static::assertSame(['test'], $event->getDeletedPrimaryKeys('blog'));
    }

    public function testCreateVersionNotVersionAware(): void
    {
        $repo = new EntityRepository(
            static::createStub(EntityDefinition::class),
            static::createStub(EntityReaderInterface::class),
            static::createStub(VersionManager::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
        );

        $this->expectExceptionObject(DataAbstractionLayerException::entityNotVersionAware(''));

        $repo->createVersion('test', Context::createDefaultContext());
    }

    public function testCreateVersionVersionAware(): void
    {
        $versionManager = $this->createMock(VersionManager::class);
        $versionManager->expects($this->once())->method('createVersion');
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('isVersionAware')->willReturn(true);

        $repo = new EntityRepository(
            $definition,
            static::createStub(EntityReaderInterface::class),
            $versionManager,
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
        );

        $repo->createVersion('test', Context::createDefaultContext());
    }

    public function testMergeVersionNotVersionAware(): void
    {
        $repo = new EntityRepository(
            static::createStub(EntityDefinition::class),
            static::createStub(EntityReaderInterface::class),
            static::createStub(VersionManager::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
        );

        $this->expectExceptionObject(DataAbstractionLayerException::entityNotVersionAware(''));

        $repo->merge('test', Context::createDefaultContext());
    }

    public function testMergeVersionVersionAware(): void
    {
        $versionManager = $this->createMock(VersionManager::class);
        $versionManager->expects($this->once())->method('merge');
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('isVersionAware')->willReturn(true);

        $repo = new EntityRepository(
            $definition,
            static::createStub(EntityReaderInterface::class),
            $versionManager,
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
        );

        $repo->merge('test', Context::createDefaultContext());
    }

    public function testCloneInvalidId(): void
    {
        $repo = new EntityRepository(
            static::createStub(EntityDefinition::class),
            static::createStub(EntityReaderInterface::class),
            static::createStub(VersionManager::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
        );

        static::expectExceptionObject(DataAbstractionLayerException::invalidEntityUuidException('test'));

        $repo->clone('test', Context::createDefaultContext(), 'test');
    }

    public function testClone(): void
    {
        $eventDispatcher = new EventDispatcher();

        $event = null;
        $eventDispatcher->addListener(EntityWrittenContainerEvent::class, static function ($inner) use (&$event): void {
            $event = $inner;
        });

        $versionManager = $this->createMock(VersionManager::class);
        $versionManager
            ->expects($this->once())
            ->method('clone')
            ->willReturn([[new EntityWriteResult('new-id', [], 'blog', EntityWriteResult::OPERATION_UPDATE)]]);

        $repo = new EntityRepository(
            static::createStub(EntityDefinition::class),
            static::createStub(EntityReaderInterface::class),
            $versionManager,
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            $eventDispatcher,
            static::createStub(EntityLoadedEventFactory::class),
        );

        $repo->clone('test', Context::createDefaultContext());

        static::assertInstanceOf(EntityWrittenContainerEvent::class, $event);
        static::assertSame(['new-id'], $event->getPrimaryKeys('blog'));
    }

    public function testSearchIdsIsRoutedThroughTheInstrumentor(): void
    {
        $operations = [];
        $repo = new EntityRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            static::createStub(VersionManager::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
            $this->recordingInstrumentor($operations),
        );

        $repo->searchIds(new Criteria(), Context::createDefaultContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH_IDS], $operations);
    }

    public function testAggregateIsRoutedThroughTheInstrumentor(): void
    {
        $operations = [];
        $repo = new EntityRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            static::createStub(VersionManager::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
            $this->recordingInstrumentor($operations),
        );

        $repo->aggregate(new Criteria(), Context::createDefaultContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_AGGREGATE], $operations);
    }

    public function testReadOnlySearchIsRoutedThroughTheInstrumentor(): void
    {
        $operations = [];
        $repo = new EntityRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            static::createStub(VersionManager::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
            $this->recordingInstrumentor($operations),
        );

        // an empty criteria needs no id lookup, so only the outer search operation is measured
        $repo->search(new Criteria(), Context::createDefaultContext());

        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH], $operations);
    }

    public function testSearchWithIdLookupDoesNotEmitANestedSearchIds(): void
    {
        $operations = [];
        $repo = new EntityRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            static::createStub(VersionManager::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
            $this->recordingInstrumentor($operations),
        );

        $criteria = new Criteria();
        $criteria->setTerm('foo');
        $repo->search($criteria, Context::createDefaultContext());

        // the id lookup runs through the private _searchIds(); the outer search is the only measured operation,
        // so its duration is not double-counted by a nested searchIds sample
        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH], $operations);
    }

    public function testSearchWithAggregationsDoesNotEmitANestedAggregate(): void
    {
        $operations = [];
        $repo = new EntityRepository(
            new BlogDefinition(),
            static::createStub(EntityReaderInterface::class),
            static::createStub(VersionManager::class),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(EntityAggregatorInterface::class),
            new EventDispatcher(),
            static::createStub(EntityLoadedEventFactory::class),
            $this->recordingInstrumentor($operations),
        );

        $criteria = new Criteria();
        $criteria->addAggregation(new TermsAggregation('agg', 'id'));
        $repo->search($criteria, Context::createDefaultContext());

        // the aggregate is a nested sub-operation (profiled, not metered); only the outer search is measured
        static::assertSame([DalSearchInstrumentor::OPERATION_SEARCH], $operations);
    }

    /**
     * An instrumentor mock that records each measured operation and transparently runs the wrapped
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
