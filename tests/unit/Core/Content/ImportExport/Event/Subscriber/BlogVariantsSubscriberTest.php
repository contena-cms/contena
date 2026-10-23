<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\ImportExport\Event\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogConfiguratorSetting\BlogConfiguratorSettingDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\ImportExport\Event\ImportExportAfterImportRecordEvent;
use Contena\Core\Content\ImportExport\Event\Subscriber\BlogVariantsSubscriber;
use Contena\Core\Content\ImportExport\Struct\Config;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Framework\Api\Sync\SyncOperation;
use Contena\Core\Framework\Api\Sync\SyncResult;
use Contena\Core\Framework\Api\Sync\SyncServiceInterface;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Event\NestedEventCollection;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(BlogVariantsSubscriber::class)]
class BlogVariantsSubscriberTest extends TestCase
{
    private Context $context;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
    }

    public function testReturnsEarlyForNonBlogSourceEntity(): void
    {
        $event = $this->createEvent('category');

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testReturnsEarlyForNonStringVariants(): void
    {
        $event = $this->createEvent(BlogDefinition::ENTITY_NAME, ['variants' => ['size: M']]);

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testReturnsEarlyForEmptyVariants(): void
    {
        $event = $this->createEvent(BlogDefinition::ENTITY_NAME, ['variants' => '']);

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testReturnsEarlyWhenWrittenEventsAreMissing(): void
    {
        $result = $this->createMock(EntityWrittenContainerEvent::class);
        $result->expects($this->once())->method('getEvents')->willReturn(null);
        $event = $this->createEvent(BlogDefinition::ENTITY_NAME, result: $result);

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testReturnsEarlyWhenNoBlogWasWritten(): void
    {
        $writtenEvent = new EntityWrittenEvent('category', [], $this->context);
        $event = $this->createEvent(
            BlogDefinition::ENTITY_NAME,
            result: new EntityWrittenContainerEvent($this->context, new NestedEventCollection([$writtenEvent]), [])
        );

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testReturnsEarlyWhenBlogWriteResultsAreEmpty(): void
    {
        $writtenEvent = new EntityWrittenEvent(BlogDefinition::ENTITY_NAME, [], $this->context);
        $event = $this->createEvent(
            BlogDefinition::ENTITY_NAME,
            result: new EntityWrittenContainerEvent($this->context, new NestedEventCollection([$writtenEvent]), [])
        );

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testReturnsEarlyForNonStringParentId(): void
    {
        $writeResult = new EntityWriteResult(
            ['id' => Uuid::randomBytes()],
            ['blogNumber' => 'parent'],
            BlogDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT
        );
        $writtenEvent = new EntityWrittenEvent(BlogDefinition::ENTITY_NAME, [$writeResult], $this->context);
        $event = $this->createEvent(
            BlogDefinition::ENTITY_NAME,
            result: new EntityWrittenContainerEvent($this->context, new NestedEventCollection([$writtenEvent]), [])
        );

        $this->createSubscriber()->onAfterImportRecord($event);
    }

    public function testCreatesBlogVariantsAndConfiguratorSettings(): void
    {
        $parentId = Uuid::randomHex();
        $groupId = Uuid::randomHex();
        $mediumOptionId = Uuid::randomHex();
        $largeOptionId = Uuid::randomHex();

        $groupRepository = StaticEntityRepository::of(PropertyGroupCollection::class, [[$groupId]]);
        $optionRepository = StaticEntityRepository::of(PropertyGroupOptionCollection::class, [[$mediumOptionId], [$largeOptionId]]);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(2))->method('executeStatement')->willReturn(1);

        $syncService = $this->createMock(SyncServiceInterface::class);
        $syncService->expects($this->once())
            ->method('sync')
            ->willReturnCallback(function (array $operations, Context $context) use ($parentId, $groupId, $mediumOptionId, $largeOptionId): SyncResult {
                static::assertSame($this->context, $context);
                static::assertCount(2, $operations);
                static::assertInstanceOf(SyncOperation::class, $operations[0]);
                static::assertInstanceOf(SyncOperation::class, $operations[1]);
                static::assertSame(BlogDefinition::ENTITY_NAME, $operations[0]->getEntity());
                static::assertSame(BlogConfiguratorSettingDefinition::ENTITY_NAME, $operations[1]->getEntity());
                $firstOperationPayload = $operations[0]->getPayload();
                $secondOperationPayload = $operations[1]->getPayload();
                static::assertCount(2, $firstOperationPayload);
                static::assertCount(2, $secondOperationPayload);

                $mediumOption = $firstOperationPayload[0];
                self::assertSame($parentId, $mediumOption['parentId']);
                self::assertSame($mediumOptionId, $mediumOption['options'][0]['id']);
                self::assertSame('M', $mediumOption['options'][0]['name']);
                self::assertSame($groupId, $mediumOption['options'][0]['group']['id']);
                self::assertSame('size', $mediumOption['options'][0]['group']['name']);

                $largeOption = $firstOperationPayload[1];
                self::assertSame($parentId, $largeOption['parentId']);
                self::assertSame($largeOptionId, $largeOption['options'][0]['id']);
                self::assertSame('L', $largeOption['options'][0]['name']);
                self::assertSame($groupId, $largeOption['options'][0]['group']['id']);
                self::assertSame('size', $largeOption['options'][0]['group']['name']);

                $mediumConfiguratorSetting = $secondOperationPayload[0];
                self::assertSame($parentId, $mediumConfiguratorSetting['blogId']);
                self::assertSame($mediumOptionId, $mediumConfiguratorSetting['optionId']);

                $largeConfiguratorSetting = $secondOperationPayload[1];
                self::assertSame($parentId, $largeConfiguratorSetting['blogId']);
                self::assertSame($largeOptionId, $largeConfiguratorSetting['optionId']);

                return new SyncResult([]);
            });

        $subscriber = new BlogVariantsSubscriber($syncService, $connection, $groupRepository, $optionRepository);
        $writeResult = new EntityWriteResult(
            $parentId,
            ['blogNumber' => 'parent'],
            BlogDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_INSERT
        );
        $event = $this->createEvent(
            BlogDefinition::ENTITY_NAME,
            ['variants' => 'size: M, L'],
            new EntityWrittenContainerEvent(
                $this->context,
                new NestedEventCollection([
                    new EntityWrittenEvent(BlogDefinition::ENTITY_NAME, [$writeResult], $this->context),
                ]),
                []
            )
        );

        $subscriber->onAfterImportRecord($event);
    }

    private function createSubscriber(): BlogVariantsSubscriber
    {
        $syncService = $this->createMock(SyncServiceInterface::class);
        $syncService->expects($this->never())->method('sync');
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('executeStatement');

        return new BlogVariantsSubscriber(
            $syncService,
            $connection,
            static::createStub(EntityRepository::class),
            static::createStub(EntityRepository::class),
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createEvent(
        string $sourceEntity,
        array $row = ['variants' => 'size: M'],
        ?EntityWrittenContainerEvent $result = null
    ): ImportExportAfterImportRecordEvent {
        return new ImportExportAfterImportRecordEvent(
            $result ?? new EntityWrittenContainerEvent($this->context, new NestedEventCollection(), []),
            [],
            $row,
            new Config([], ['sourceEntity' => $sourceEntity], []),
            $this->context,
        );
    }
}
