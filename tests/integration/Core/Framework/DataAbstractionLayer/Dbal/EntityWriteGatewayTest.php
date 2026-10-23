<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSetAware;
use Contena\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Country\CountryCollection;
use Contena\Core\System\Country\CountryEntity;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Doctrine\FailingDeleteConnection;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class EntityWriteGatewayTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private IdsCollection $ids;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->ids = new IdsCollection();

        $this->createBlog($this->ids->create('blog'));
        $this->createBlog($this->ids->create('blog-2'));
    }

    public function testFetchChangeSet(): void
    {
        $update = ['id' => $this->ids->get('blog'), 'stock' => 100];

        EventHookDispatcher::fromContainer(static::getContainer())->on(PreWriteValidationEvent::class, static function (PreWriteValidationEvent $event): void {
            foreach ($event->getCommands() as $command) {
                if (!$command instanceof ChangeSetAware) {
                    continue;
                }
                $command->requestChangeSet();
            }
        });

        $result = $this->blogRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(BlogDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('stock'));
        static::assertSame('1', $changeSet->getBefore('stock'));
        static::assertSame(100, $changeSet->getAfter('stock'));
    }

    public function testUpdateWithSameValue(): void
    {
        $id = $this->ids->get('blog');

        $update = ['id' => $id, 'stock' => 1];

        EventHookDispatcher::fromContainer(static::getContainer())->on(PreWriteValidationEvent::class, static function (PreWriteValidationEvent $event): void {
            foreach ($event->getCommands() as $command) {
                if (!$command instanceof ChangeSetAware) {
                    continue;
                }
                $command->requestChangeSet();
            }
        });

        $result = $this->blogRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(BlogDefinition::ENTITY_NAME, $result);

        static::assertFalse($changeSet->hasChanged('stock'));
        static::assertSame('1', $changeSet->getBefore('stock'));
        static::assertNull($changeSet->getAfter('stock'));
    }

    public function testChangeSetWithDeletes(): void
    {
        $id = $this->ids->get('blog');

        EventHookDispatcher::fromContainer(static::getContainer())->on(PreWriteValidationEvent::class, static function (PreWriteValidationEvent $event): void {
            foreach ($event->getCommands() as $command) {
                if (!$command instanceof ChangeSetAware) {
                    continue;
                }
                $command->requestChangeSet();
            }
        });

        $result = $this->blogRepository->delete([['id' => $id]], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(BlogDefinition::ENTITY_NAME, $result);
        static::assertTrue($changeSet->hasChanged('id'));
        static::assertTrue($changeSet->hasChanged('blog_number'));
        static::assertTrue($changeSet->hasChanged('price'));

        static::assertNull($changeSet->getAfter('id'));
        static::assertNull($changeSet->getAfter('blog_number'));
        static::assertNull($changeSet->getAfter('price'));

        $changeSet = $this->getChangeSet(BlogCategoryDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('blog_id'));
        static::assertSame(Uuid::fromHexToBytes($id), $changeSet->getBefore('blog_id'));
        static::assertNull($changeSet->getAfter('blog_id'));
    }

    public function testChangeSetWithTranslations(): void
    {
        $id = $this->ids->get('blog');

        $update = ['id' => $id, 'name' => 'updated'];

        EventHookDispatcher::fromContainer(static::getContainer())->on(PreWriteValidationEvent::class, static function (PreWriteValidationEvent $event): void {
            foreach ($event->getCommands() as $command) {
                if (!$command instanceof ChangeSetAware) {
                    continue;
                }
                $command->requestChangeSet();
            }
        });

        $result = $this->blogRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(BlogTranslationDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('name'));
        static::assertSame('test', $changeSet->getBefore('name'));
        static::assertSame('updated', $changeSet->getAfter('name'));
    }

    public function testChangeSetWithOneToMany(): void
    {
        $id = $this->ids->get('blog');

        $update = [
            'id' => $id,
            'visibilities' => [
                ['id' => $id, 'visibility' => BlogVisibilityDefinition::VISIBILITY_LINK],
            ],
        ];

        EventHookDispatcher::fromContainer(static::getContainer())->on(PreWriteValidationEvent::class, static function (PreWriteValidationEvent $event): void {
            foreach ($event->getCommands() as $command) {
                if (!$command instanceof ChangeSetAware) {
                    continue;
                }
                $command->requestChangeSet();
            }
        });

        $result = $this->blogRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(BlogVisibilityDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('visibility'));
        static::assertSame(BlogVisibilityDefinition::VISIBILITY_ALL, (int) $changeSet->getBefore('visibility'));
        static::assertSame(BlogVisibilityDefinition::VISIBILITY_LINK, (int) $changeSet->getAfter('visibility'));
    }

    public function testChangeSetWithManyToOne(): void
    {
        $id = $this->ids->get('blog');
        $newId = Uuid::randomHex();

        $update = [
            'id' => $id,
            'manufacturer' => [
                'id' => $newId,
                'name' => 'new manufacturer',
            ],
        ];

        EventHookDispatcher::fromContainer(static::getContainer())->on(PreWriteValidationEvent::class, static function (PreWriteValidationEvent $event): void {
            foreach ($event->getCommands() as $command) {
                if (!$command instanceof ChangeSetAware) {
                    continue;
                }
                $command->requestChangeSet();
            }
        });

        $result = $this->blogRepository->update([$update], Context::createDefaultContext());

        $changeSet = $this->getChangeSet(BlogDefinition::ENTITY_NAME, $result);

        static::assertTrue($changeSet->hasChanged('blog_manufacturer_id'));
        static::assertSame($id, Uuid::fromBytesToHex($changeSet->getBefore('blog_manufacturer_id')));
        static::assertSame($newId, Uuid::fromBytesToHex($changeSet->getAfter('blog_manufacturer_id')));
    }

    public function testChangeSetWithMultipleCommandsForSameEntityType(): void
    {
        $blogId1 = $this->ids->get('blog');
        $blogId2 = $this->ids->get('blog-2');

        $updates = [
            ['id' => $blogId1, 'stock' => 100],
            ['id' => $blogId2, 'stock' => 50],
        ];

        EventHookDispatcher::fromContainer(static::getContainer())->on(PreWriteValidationEvent::class, static function (PreWriteValidationEvent $event): void {
            foreach ($event->getCommands() as $command) {
                if (!$command instanceof ChangeSetAware) {
                    continue;
                }
                $command->requestChangeSet();
            }
        });

        $result = $this->blogRepository->update($updates, Context::createDefaultContext());

        $changeSets = $this->getChangeSets(BlogDefinition::ENTITY_NAME, $result, 2);
        $changeSetForBlog1 = array_values(array_filter($changeSets, static function (ChangeSet $changeSet) use (&$blogId1) {
            return $changeSet->getBefore('id') === hex2bin($blogId1);
        }))[0];
        $changeSetForBlog2 = array_values(array_filter($changeSets, static function (ChangeSet $changeSet) use (&$blogId2) {
            return $changeSet->getBefore('id') === hex2bin($blogId2);
        }))[0];

        static::assertNotNull($changeSetForBlog1);
        static::assertTrue($changeSetForBlog1->hasChanged('stock'));
        static::assertSame('1', $changeSetForBlog1->getBefore('stock'));
        static::assertSame(100, $changeSetForBlog1->getAfter('stock'));

        static::assertNotNull($changeSetForBlog2);
        static::assertTrue($changeSetForBlog2->hasChanged('stock'));
        static::assertSame('1', $changeSetForBlog2->getBefore('stock'));
        static::assertSame(50, $changeSetForBlog2->getAfter('stock'));
    }

    public function testCustomFieldsMergeWithIntegers(): void
    {
        $id = Uuid::randomHex();

        $data = [
            'id' => $id,
            'name' => 'test',
            'customFields' => ['a' => 1234],
        ];

        /** @var EntityRepository<CountryCollection> $countryRepo */
        $countryRepo = static::getContainer()->get('country.repository');

        $countryRepo->upsert([$data], Context::createDefaultContext());

        $data['customFields']['b'] = 1;
        $data['customFields']['c'] = 1.56;
        $data['customFields']['d'] = true;
        $data['customFields']['e'] = ['a'];
        $data['customFields']['f'] = new \stdClass();
        $data['customFields']['g'] = 'test';

        $countryRepo->upsert([$data], Context::createDefaultContext());

        $country = $countryRepo
            ->search(new Criteria([$id]), Context::createDefaultContext())
            ->getEntities()
            ->first();

        static::assertInstanceOf(CountryEntity::class, $country);

        $customFields = $country->getCustomFields();
        static::assertNotNull($customFields);

        static::assertIsInt($customFields['a']);
        static::assertIsInt($customFields['b']);
        static::assertSame(1234, $customFields['a']);
        static::assertSame(1, $customFields['b']);

        static::assertIsFloat($customFields['c']);
        static::assertSame(1.56, $customFields['c']);

        static::assertIsBool($customFields['d']);
        static::assertTrue($customFields['d']);

        static::assertIsArray($customFields['e']);
        static::assertSame(['a'], $customFields['e']);

        static::assertIsArray($customFields['f']);
        static::assertCount(0, $customFields['f']);
        static::assertSame($customFields['g'], 'test');
    }

    public function testEntityDeleteEventNotDispatched(): void
    {
        $id = $this->ids->get('blog');

        $update = ['id' => $id, 'stock' => 1];

        $spy = $this->eventListenerCalledSpy();

        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityDeleteEvent::class, $spy);

        $this->blogRepository->update([$update], Context::createDefaultContext());

        static::assertNull($spy->event);
    }

    public function testEntityDeleteEventDispatched(): void
    {
        $id1 = $this->ids->get('blog');
        $id2 = $this->ids->get('blog-2');

        $delete = [['id' => $id1], ['id' => $id2]];

        $spy = $this->eventListenerCalledSpy();

        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityDeleteEvent::class, $spy);

        $this->blogRepository->delete($delete, Context::createDefaultContext());

        /** @var EntityDeleteEvent $event */
        $event = $spy->event;
        static::assertInstanceOf(EntityDeleteEvent::class, $event);
        static::assertTrue($event->filled());
        static::assertSame([$id1, $id2], $event->getIds('blog'));
    }

    public function testEntityDeleteEventSuccessCallbacksCalled(): void
    {
        $id1 = $this->ids->get('blog');

        $delete = [['id' => $id1]];

        $successSpy = $this->callbackSpy();
        $errorSpy = $this->callbackSpy();

        $spy = $this->eventListenerCalledSpy(static function (EntityDeleteEvent $event) use ($successSpy, $errorSpy): void {
            $event->addSuccess($successSpy(...));
            $event->addError($errorSpy(...));
        });

        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityDeleteEvent::class, $spy);

        $this->blogRepository->delete($delete, Context::createDefaultContext());

        static::assertTrue($successSpy->called);
        static::assertFalse($errorSpy->called);
    }

    public function testMultipleCallbacksAreCalledOnTheSameEntityDeleteEvent(): void
    {
        $id = $this->ids->get('blog');

        $delete = [['id' => $id]];

        $successSpy1 = $this->callbackSpy();
        $successSpy2 = $this->callbackSpy();

        $eventSpy1 = $this->eventListenerCalledSpy(static fn (EntityDeleteEvent $event) => $event->addSuccess($successSpy1(...)));
        $eventSpy2 = $this->eventListenerCalledSpy(static fn (EntityDeleteEvent $event) => $event->addSuccess($successSpy2(...)));

        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityDeleteEvent::class, $eventSpy1);
        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityDeleteEvent::class, $eventSpy2);

        $this->blogRepository->delete($delete, Context::createDefaultContext());

        static::assertTrue($successSpy1->called);
        static::assertTrue($successSpy2->called);
    }

    /**
     * A failing delete fires both the general EntityWriteEvent and the specific EntityDeleteEvent;
     * assert each one's error callbacks are invoked (and success callbacks are not).
     */
    #[DataProvider('errorCallbackEventProvider')]
    #[TestDox('error callbacks are invoked when a gateway operation fails')]
    public function testErrorCallbacksAreInvokedOnEvent(string $eventClass): void
    {
        $delete = [['id' => Uuid::randomBytes(), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]];

        $realConnection = static::getContainer()->get(Connection::class);

        $connection = new FailingDeleteConnection(
            array_merge(
                $realConnection->getParams(),
                [
                    'dbname' => $realConnection->getDatabase() ?? '',
                ]
            ),
            $realConnection->getDriver(),
            $realConnection->getConfiguration(),
        );

        $successSpy = $this->callbackSpy();
        $errorSpy = $this->callbackSpy();

        // a Closure satisfies both EntityDeleteEvent::addSuccess(\Closure) and EntityWriteEvent::addSuccess(callable)
        $spy = $this->eventListenerCalledSpy(static function (EntityDeleteEvent|EntityWriteEvent $event) use ($successSpy, $errorSpy): void {
            $event->addSuccess($successSpy(...));
            $event->addError($errorSpy(...));
        });

        EventHookDispatcher::fromContainer(static::getContainer())->on($eventClass, $spy);

        $definitionRegistry = static::getContainer()->get(DefinitionInstanceRegistry::class);

        $gateway = new EntityWriteGateway(
            1,
            $connection,
            static::getContainer()->get('event_dispatcher'),
            static::getContainer()->get(ExceptionHandlerRegistry::class),
            $definitionRegistry
        );

        $writeContext = WriteContext::createFromContext(Context::createDefaultContext());

        $command = new DeleteCommand(
            $definitionRegistry->getByEntityName('blog'),
            $delete[0],
            new EntityExistence('blog', $delete[0], true, true, true, [])
        );

        $exceptionThrown = null;

        try {
            $gateway->execute([$command], $writeContext);
        } catch (Exception $exception) {
            $exceptionThrown = $exception;
        }

        static::assertInstanceOf(Exception::class, $exceptionThrown);
        static::assertSame('test', $exceptionThrown->getMessage());

        static::assertTrue($errorSpy->called);
        static::assertFalse($successSpy->called);
    }

    /**
     * @return \Generator<string, array{class-string}>
     */
    public static function errorCallbackEventProvider(): \Generator
    {
        yield 'delete event' => [EntityDeleteEvent::class];

        yield 'write event' => [EntityWriteEvent::class];
    }

    /**
     * @param 'delete'|'upsert'|'update'|'create' $method
     */
    #[DataProvider('methodProvider')]
    public function testEntityWriteEventDispatched(string $method): void
    {
        $id1 = $this->ids->get('p-1');
        $id2 = $this->ids->get('p-2');

        $p1Data = $this->getBlogData($id1);
        $p2Data = $this->getBlogData($id2);

        if ($method !== 'create') {
            $this->blogRepository->create([$p1Data, $p2Data], Context::createDefaultContext());
        }

        $ids = [['id' => $id1], ['id' => $id2]];

        $spy = $this->eventListenerCalledSpy();

        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityWriteEvent::class, $spy);

        match ($method) {
            'delete' => $this->blogRepository->delete($ids, Context::createDefaultContext()),
            'upsert' => $this->blogRepository->upsert($ids, Context::createDefaultContext()),
            'update' => $this->blogRepository->update($ids, Context::createDefaultContext()),
            'create' => $this->blogRepository->create([$p1Data, $p2Data], Context::createDefaultContext()),
        };

        static::assertInstanceOf(EntityWriteEvent::class, $spy->event);
        static::assertSame([$id1, $id2], $spy->event->getIds('blog'));
    }

    /**
     * @return iterable<array<string>>
     */
    public static function methodProvider(): iterable
    {
        yield 'method create' => ['create'];
        yield 'method upsert' => ['upsert'];
        yield 'method update' => ['update'];
        yield 'method delete' => ['delete'];
    }

    public function testEntityWriteEventSuccessCallbacksCalled(): void
    {
        $id1 = $this->ids->get('blog');

        $delete = [['id' => $id1]];

        $successSpy = $this->callbackSpy();
        $errorSpy = $this->callbackSpy();

        $spy = $this->eventListenerCalledSpy(static function (EntityWriteEvent $event) use ($successSpy, $errorSpy): void {
            $event->addSuccess($successSpy);
            $event->addError($errorSpy);
        });

        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityWriteEvent::class, $spy);

        $this->blogRepository->delete($delete, Context::createDefaultContext());

        static::assertTrue($successSpy->called);
        static::assertFalse($errorSpy->called);
    }

    public function testMultipleCallbacksAreCalledOnTheSameEntityWriteEvent(): void
    {
        $id = $this->ids->get('blog');

        $delete = [['id' => $id, 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)]];

        $successSpy1 = $this->callbackSpy();
        $successSpy2 = $this->callbackSpy();

        $eventSpy1 = $this->eventListenerCalledSpy(static fn (EntityWriteEvent $event) => $event->addSuccess($successSpy1));
        $eventSpy2 = $this->eventListenerCalledSpy(static fn (EntityWriteEvent $event) => $event->addSuccess($successSpy2));

        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityWriteEvent::class, $eventSpy1);
        EventHookDispatcher::fromContainer(static::getContainer())->on(EntityWriteEvent::class, $eventSpy2);

        $this->blogRepository->delete($delete, Context::createDefaultContext());

        static::assertTrue($successSpy1->called);
        static::assertTrue($successSpy2->called);
    }

    /**
     * @return callable(mixed...): void&object{called: bool}
     */
    private function callbackSpy(): callable
    {
        return new class {
            public bool $called = false;

            public function __invoke(): void
            {
                $this->called = true;
            }
        };
    }

    /**
     * @return callable(ContenaEvent): void&object{event: ?ContenaEvent}
     */
    private function eventListenerCalledSpy(?\Closure $callback = null): callable
    {
        return new class($callback) {
            public ?ContenaEvent $event = null;

            public function __construct(private ?\Closure $callback = null)
            {
            }

            public function __invoke(ContenaEvent $event): void
            {
                $this->event = $event;

                if ($this->callback instanceof \Closure) {
                    ($this->callback)($event);
                }
            }
        };
    }

    private function createBlog(string $id): void
    {
        $this->blogRepository->create([$this->getBlogData($id)], Context::createDefaultContext());
    }

    /**
     * @return array<string, mixed>
     */
    private function getBlogData(?string $id = null): array
    {
        $id ??= Uuid::randomHex();

        return [
            'id' => $id,
            'name' => 'test',
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'manufacturer' => ['id' => $id, 'name' => 'test'],
            'tax' => ['id' => $id, 'name' => 'test', 'taxRate' => 15],
            'categories' => [
                ['id' => $id, 'name' => 'test'],
            ],
            'visibilities' => [
                ['id' => $id, 'channelId' => TestDefaults::CHANNEL, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];
    }

    private function getChangeSet(string $entity, EntityWrittenContainerEvent $result): ChangeSet
    {
        $event = $result->getEventByEntityName($entity);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount(1, $event->getWriteResults());

        $changeSet = $event->getWriteResults()[0]->getChangeSet();
        static::assertInstanceOf(ChangeSet::class, $changeSet);

        return $changeSet;
    }

    /**
     * @return array<ChangeSet>
     */
    private function getChangeSets(string $entity, EntityWrittenContainerEvent $result, int $expectedSize): array
    {
        $event = $result->getEventByEntityName($entity);
        static::assertInstanceOf(EntityWrittenEvent::class, $event);
        static::assertCount($expectedSize, $event->getWriteResults());

        return array_map(static function (EntityWriteResult $writeResult) {
            $changeSet = $writeResult->getChangeSet();
            static::assertInstanceOf(ChangeSet::class, $changeSet);

            return $changeSet;
        }, $event->getWriteResults());
    }
}
