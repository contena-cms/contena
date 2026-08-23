<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\Api\Sync;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\ApiException;
use Contena\Core\Framework\Api\Context\AdminApiSource;
use Contena\Core\Framework\Api\Sync\SyncBehavior;
use Contena\Core\Framework\Api\Sync\SyncFkResolver;
use Contena\Core\Framework\Api\Sync\SyncOperation;
use Contena\Core\Framework\Api\Sync\SyncResult;
use Contena\Core\Framework\Api\Sync\SyncService;
use Contena\Core\Framework\Api\Sync\Telemetry\SyncMetricsInstrumentor;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteResult;
use Contena\Core\Framework\Event\NestedEventDispatcher;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[CoversClass(SyncService::class)]
class SyncServiceTest extends TestCase
{
    public function testSyncSingleOperation(): void
    {
        $writeResult = new WriteResult(
            [
                'blog' => [new EntityWriteResult('deleted-id', [], 'blog', EntityWriteResult::OPERATION_DELETE)],
            ],
            [],
            [
                'blog' => [new EntityWriteResult('created-id', [], 'blog', EntityWriteResult::OPERATION_INSERT)],
            ]
        );

        $writer = $this->createMock(EntityWriterInterface::class);
        $writer
            ->expects($this->once())
            ->method('sync')
            ->willReturn($writeResult);

        $service = new SyncService(
            $writer,
            static::createStub(EventDispatcherInterface::class),
            new StaticDefinitionInstanceRegistry(
                [BlogDefinition::class],
                static::createStub(ValidatorInterface::class),
                static::createStub(EntityWriteGatewayInterface::class),
            ),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(RequestCriteriaBuilder::class),
            static::createStub(AclCriteriaValidator::class),
            static::createStub(SyncFkResolver::class),
            $this->createSyncMetricsStub(),
        );

        $upsert = new SyncOperation('foo', 'blog', SyncOperation::ACTION_UPSERT, [
            ['id' => '1', 'name' => 'foo'],
            ['id' => '2', 'name' => 'bar'],
        ]);

        $delete = new SyncOperation('delete-foo', 'blog', SyncOperation::ACTION_DELETE, [
            ['id' => '1'],
            ['id' => '2'],
        ]);

        $behavior = new SyncBehavior('disable-indexing', ['blog.indexer']);
        $result = $service->sync([$upsert, $delete], Context::createDefaultContext(), $behavior);

        static::assertSame([
            'blog' => [
                'deleted-id',
            ],
        ], $result->getDeleted());

        static::assertSame([
            'blog' => [
                'created-id',
            ],
        ], $result->getData());

        static::assertSame([], $result->getNotFound());
    }

    public function testCriteriaGetsNoLimit(): void
    {
        $ids = new IdsCollection();
        $operations = [
            new SyncOperation(
                key: 'foo',
                entity: 'blog_category',
                action: SyncOperation::ACTION_DELETE,
                payload: [],
                criteria: [['type' => 'equals', 'field' => 'blogId', 'value' => $ids->get('foo')]]
            ),
        ];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog_category.blogId', $ids->get('foo')));

        $registry = new StaticDefinitionInstanceRegistry(
            [BlogCategoryDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $searcher = $this->createMock(EntitySearcher::class);
        $searcher
            ->expects($this->once())
            ->method('search')
            ->with($registry->get(BlogCategoryDefinition::class), $criteria);

        $service = new SyncService(
            static::createStub(EntityWriter::class),
            new EventDispatcher(),
            $registry,
            $searcher,
            new RequestCriteriaBuilder(
                new AggregationParser(),
                static::createStub(ApiCriteriaValidator::class),
                new CriteriaArrayConverter(new AggregationParser()),
                new CompressedCriteriaDecoder(),
                100
            ),
            static::createStub(AclCriteriaValidator::class),
            static::createStub(SyncFkResolver::class),
            $this->createSyncMetricsStub(),
        );

        $service->sync($operations, Context::createCLIContext(), new SyncBehavior());
    }

    public function testCriteriaDeleteDoesNotSearchWithoutReadPrivileges(): void
    {
        $filter = [['type' => 'equals', 'field' => 'name', 'value' => 'Blog title']];
        $criteria = new Criteria()->addFilter(new EqualsFilter('name', 'Blog title'));

        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);
        $criteriaBuilder->expects($this->once())
            ->method('fromArray')
            ->with(['filter' => $filter])
            ->willReturn($criteria);

        $criteriaValidator = $this->createMock(AclCriteriaValidator::class);
        $criteriaValidator->expects($this->once())
            ->method('validate')
            ->willReturn(['blog:read']);

        $searcher = $this->createMock(EntitySearcherInterface::class);
        $searcher->expects($this->never())->method('search');

        $service = new SyncService(
            static::createStub(EntityWriterInterface::class),
            static::createStub(EventDispatcherInterface::class),
            new StaticDefinitionInstanceRegistry(
                [BlogDefinition::class],
                static::createStub(ValidatorInterface::class),
                static::createStub(EntityWriteGatewayInterface::class),
            ),
            $searcher,
            $criteriaBuilder,
            $criteriaValidator,
            static::createStub(SyncFkResolver::class),
            $this->createSyncMetricsStub(),
        );

        $this->expectExceptionObject(ApiException::missingPrivileges(['blog:read']));

        $service->sync([
            new SyncOperation('delete-blogs', 'blog', SyncOperation::ACTION_DELETE, [], $filter),
        ], Context::createDefaultContext(), new SyncBehavior());
    }

    public function testWrittenEventsAreDispatchedInSystemScopeWithOriginalSource(): void
    {
        $writer = $this->createMock(EntityWriterInterface::class);
        $writer
            ->expects($this->once())
            ->method('sync')
            ->willReturn(new WriteResult([], [], [
                'blog' => [new EntityWriteResult('created-id', [], 'blog', EntityWriteResult::OPERATION_INSERT)],
            ]));

        $dispatcher = new EventDispatcher();
        $eventDispatcher = new NestedEventDispatcher($dispatcher);

        $source = new AdminApiSource('user-id');
        $context = Context::createDefaultContext($source);

        $containerListenerWasCalled = false;
        $dispatcher->addListener(EntityWrittenContainerEvent::class, static function (EntityWrittenContainerEvent $event) use (&$containerListenerWasCalled, $source): void {
            $containerListenerWasCalled = true;
            $eventSource = $event->getContext()->getSource();

            static::assertSame(Context::SYSTEM_SCOPE, $event->getContext()->getScope());
            static::assertTrue($event->getContext()->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
            static::assertInstanceOf(AdminApiSource::class, $eventSource);
            static::assertSame($source->getUserId(), $eventSource->getUserId());
        });

        $nestedListenerWasCalled = false;
        $dispatcher->addListener('blog.written', static function (EntityWrittenEvent $event) use (&$nestedListenerWasCalled, $source): void {
            $nestedListenerWasCalled = true;
            $eventSource = $event->getContext()->getSource();

            static::assertSame(Context::SYSTEM_SCOPE, $event->getContext()->getScope());
            static::assertTrue($event->getContext()->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
            static::assertInstanceOf(AdminApiSource::class, $eventSource);
            static::assertSame($source->getUserId(), $eventSource->getUserId());
        });

        $service = new SyncService(
            $writer,
            $eventDispatcher,
            new StaticDefinitionInstanceRegistry(
                [BlogDefinition::class],
                static::createStub(ValidatorInterface::class),
                static::createStub(EntityWriteGatewayInterface::class),
            ),
            static::createStub(EntitySearcherInterface::class),
            static::createStub(RequestCriteriaBuilder::class),
            static::createStub(AclCriteriaValidator::class),
            static::createStub(SyncFkResolver::class),
            $this->createSyncMetricsStub(),
        );

        $service->sync(
            [new SyncOperation('delete-blog', 'blog', SyncOperation::ACTION_DELETE, [['id' => 'created-id']])],
            $context,
            new SyncBehavior()
        );

        static::assertTrue($containerListenerWasCalled);
        static::assertTrue($nestedListenerWasCalled);
        static::assertSame(Context::USER_SCOPE, $context->getScope());
        static::assertFalse($context->hasState(Context::SYSTEM_SCOPE_DAL_WRITE_EVENT));
    }

    public function testWildcardDeleteForMappingEntities(): void
    {
        $writer = $this->createMock(EntityWriterInterface::class);
        $writer
            ->expects($this->once())
            ->method('sync')
            ->willReturnCallback(static function ($operations) {
                static::assertCount(1, $operations);
                static::assertInstanceOf(SyncOperation::class, $operations[0]);

                $operation = $operations[0];

                static::assertCount(4, $operation->getPayload());

                $map = \array_map(static function (array $payload) {
                    return $payload['blogId'] . '-' . $payload['categoryId'];
                }, $operation->getPayload());

                static::assertContains('blog-1-category-1', $map);
                static::assertContains('blog-1-category-2', $map);
                static::assertContains('blog-2-category-1', $map);
                static::assertContains('blog-2-category-2', $map);

                return new WriteResult([]);
            });

        $searcher = $this->createMock(EntitySearcherInterface::class);

        $criteriaBuilder = $this->createMock(RequestCriteriaBuilder::class);

        $filter = [
            ['type' => 'equalsAny', 'field' => 'blogId', 'value' => ['blog-1', 'blog-2']],
        ];

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('blogId', ['blog-1', 'blog-2']));

        $criteriaBuilder->expects($this->once())
            ->method('fromArray')
            ->with(['filter' => $filter])
            ->willReturn($criteria);

        $data = [
            'blog-1-category-1' => ['primaryKey' => ['blogId' => 'blog-1', 'categoryId' => 'category-1'], 'data' => []],
            'blog-1-category-2' => ['primaryKey' => ['blogId' => 'blog-1', 'categoryId' => 'category-2'], 'data' => []],
            'blog-2-category-1' => ['primaryKey' => ['blogId' => 'blog-2', 'categoryId' => 'category-1'], 'data' => []],
            'blog-2-category-2' => ['primaryKey' => ['blogId' => 'blog-2', 'categoryId' => 'category-2'], 'data' => []],
        ];

        $ids = new IdSearchResult(4, $data, new Criteria(), Context::createDefaultContext());

        $searcher->expects($this->once())
            ->method('search')
            ->willReturn($ids);

        $service = new SyncService(
            $writer,
            static::createStub(EventDispatcherInterface::class),
            new StaticDefinitionInstanceRegistry(
                [BlogCategoryDefinition::class],
                static::createStub(ValidatorInterface::class),
                static::createStub(EntityWriteGatewayInterface::class),
            ),
            $searcher,
            $criteriaBuilder,
            static::createStub(AclCriteriaValidator::class),
            static::createStub(SyncFkResolver::class),
            $this->createSyncMetricsStub(),
        );

        $delete = new SyncOperation(
            'delete-mapping',
            'blog_category',
            SyncOperation::ACTION_DELETE,
            [],
            $filter
        );

        $behavior = new SyncBehavior('disable-indexing', ['blog.indexer']);

        $service->sync([$delete], Context::createDefaultContext(), $behavior);
    }

    private function createSyncMetricsStub(): SyncMetricsInstrumentor
    {
        $syncMetrics = static::createStub(SyncMetricsInstrumentor::class);
        $syncMetrics
            ->method('measure')
            ->willReturnCallback(static fn (array $operations, SyncBehavior $behavior, \Closure $callback): SyncResult => $callback());

        return $syncMetrics;
    }
}
