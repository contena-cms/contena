<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Blog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingDefinition;
use Contena\Core\Content\BlogStream\Aggregate\BlogStreamFilter\BlogStreamFilterDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Elasticsearch\Blog\BlogCustomFieldsUsedUpdater;
use Contena\Elasticsearch\Blog\ElasticsearchCustomFieldsMappingHelper;
use Contena\Elasticsearch\Framework\ElasticsearchHelper;

/**
 * @internal
 */
#[CoversClass(BlogCustomFieldsUsedUpdater::class)]
class BlogCustomFieldsUsedUpdaterTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        static::assertSame([
            BlogSortingDefinition::ENTITY_NAME . '.written' => 'blogSortingWritten',
            BlogStreamFilterDefinition::ENTITY_NAME . '.written' => 'blogStreamFilterWritten',
        ], BlogCustomFieldsUsedUpdater::getSubscribedEvents());
    }

    public function testBlogSortingNoActionWhenElasticsearchDisabled(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(false);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->never())
            ->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            static::createStub(Connection::class)
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testBlogStreamNoActionWhenElasticsearchDisabled(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(false);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->never())
            ->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            static::createStub(Connection::class)
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogStreamFilterWritten($event);
    }

    public function testBlogSortingWithCustomFieldTriggersIndexing(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.test_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ['field' => 'blog.name', 'order' => 'asc', 'priority' => 0, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'test_field' => 'int',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['test_field']) && $fields['test_field']['type'] === 'long';
            }));

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testBlogSortingWithoutFieldsInPayloadDoesNotTriggerIndexing(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            static::createStub(Connection::class)
        );

        // Payload without 'fields' - e.g., only updating priority
        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testBlogStreamFilterWithCustomFieldTriggersIndexing(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $blogStreamFilterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['stream_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'stream_field' => 'text',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['stream_field']) && $fields['stream_field']['type'] === 'keyword';
            }));

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogStreamFilterWritten($event);
    }

    public function testMultipleBlogStreamFiltersAreProcessed(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $blogStreamFilterId1 = Uuid::randomHex();
        $blogStreamFilterId2 = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['field1', 'field2']);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'field1' => 'int',
                'field2' => 'text',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogStreamFilterWritten($event);
    }

    public function testNoCustomFieldsInSortingDoesNotTriggerIndexing(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testBlogStreamFilterWithNoCustomFieldsDoesNotTriggerIndexing(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $blogStreamFilterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        // Returns empty array - no custom fields found
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogStreamFilterWritten($event);
    }

    public function testMultipleBlogSortingsAreProcessed(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.field1', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
                json_encode([
                    ['field' => 'customFields.field2', 'order' => 'desc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'field1' => 'int',
                'field2' => 'bool',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['field1']) && $fields['field1']['type'] === 'long'
                    && isset($fields['field2']) && $fields['field2']['type'] === 'boolean';
            }));

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testBlogSortingUpdateOperationTriggersIndexing(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.updated_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'updated_field' => 'float',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['updated_field']) && $fields['updated_field']['type'] === 'double';
            }));

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testCustomFieldNotFoundInDatabaseDoesNotTriggerIndexing(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.nonexistent_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        // Returns empty - custom field not found in database
        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testBlogStreamFilterUpdateWithFieldChange(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $blogStreamFilterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['new_filter_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'new_filter_field' => 'datetime',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['new_filter_field'])
                    && $fields['new_filter_field']['type'] === 'date';
            }));

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogStreamFilterWritten($event);
    }

    public function testBlogStreamWrittenWithNoCustomFieldFilters(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $blogStreamFilterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogStreamFilterWritten($event);
    }

    public function testBlogSortingAndStreamEventsProcessedIndependently(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->exactly(2))
            ->method('fetchFirstColumn')
            ->willReturnOnConsecutiveCalls(
                [
                    json_encode([
                        ['field' => 'customFields.sorting_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ]),
                ],
                ['filter_field']
            );

        $connection
            ->expects($this->exactly(2))
            ->method('fetchAllKeyValue')
            ->willReturnOnConsecutiveCalls(
                ['sorting_field' => 'int'],
                ['filter_field' => 'text']
            );

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->exactly(2))->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        // Process blog sorting event
        $sortingWriteResults = [
        ];

        $sortingEvent = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $sortingWriteResults, Context::createDefaultContext());
        $updater->blogSortingWritten($sortingEvent);

        $results = [
        ];

        $event = new EntityWrittenEvent(BlogStreamFilterDefinition::ENTITY_NAME, $results, Context::createDefaultContext());
        $updater->blogStreamFilterWritten($event);
    }

    public function testDuplicateCustomFieldsAreDeduplicatedInSorting(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.same_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ['field' => 'customFields.same_field', 'order' => 'desc', 'priority' => 0, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'same_field' => 'int',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return \count($fields) === 1 && isset($fields['same_field']);
            }));

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testBlogSortingWithFieldsTriggersIndexing(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.combined_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'combined_field' => 'bool',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['combined_field']) && $fields['combined_field']['type'] === 'boolean';
            }));

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testBlogSortingDeleteOperationIsProcessed(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            static::createStub(Connection::class)
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testBlogStreamFilterDeleteOperationIsSkipped(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogStreamFilterWritten($event);
    }

    public function testBlogSortingWithEmptyWriteResults(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            static::createStub(Connection::class)
        );

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, [], Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testBlogStreamWithEmptyWriteResults(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            static::createStub(Connection::class)
        );

        $event = new EntityWrittenEvent(BlogStreamFilterDefinition::ENTITY_NAME, [], Context::createDefaultContext());

        $updater->blogStreamFilterWritten($event);
    }

    public function testBlogSortingWithMixedFieldTypes(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.int_field', 'order' => 'asc', 'priority' => 5, 'naturalSorting' => false],
                    ['field' => 'customFields.float_field', 'order' => 'asc', 'priority' => 4, 'naturalSorting' => false],
                    ['field' => 'customFields.bool_field', 'order' => 'asc', 'priority' => 3, 'naturalSorting' => false],
                    ['field' => 'customFields.datetime_field', 'order' => 'asc', 'priority' => 2, 'naturalSorting' => false],
                    ['field' => 'customFields.json_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'int_field' => 'int',
                'float_field' => 'float',
                'bool_field' => 'bool',
                'datetime_field' => 'datetime',
                'json_field' => 'json',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return isset($fields['int_field']) && $fields['int_field']['type'] === 'long'
                    && isset($fields['float_field']) && $fields['float_field']['type'] === 'double'
                    && isset($fields['bool_field']) && $fields['bool_field']['type'] === 'boolean'
                    && isset($fields['datetime_field']) && $fields['datetime_field']['type'] === 'date'
                    && isset($fields['json_field']) && $fields['json_field']['type'] === 'object';
            }));

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testOnlyBlogSortingEventWithNoFilters(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.sorting_only_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'sorting_only_field' => 'text',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testOnlyBlogStreamEventWithNoSorting(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $blogStreamFilterId = Uuid::randomHex();

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn(['filter_only_field']);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'filter_only_field' => 'int',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogStreamFilterWritten($event);
    }

    public function testBlogSortingWithNonStringPrimaryKeyIsSkipped(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            static::createStub(Connection::class)
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testBlogStreamFilterWithNonStringPrimaryKeyIsSkipped(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            static::createStub(Connection::class)
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogStreamFilterDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogStreamFilterWritten($event);
    }

    public function testSortingWithInvalidJsonFromDatabaseIsSkipped(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                'not-valid-json{{{',
                json_encode([
                    ['field' => 'customFields.valid_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection
            ->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'valid_field' => 'int',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper
            ->expects($this->once())
            ->method('createFieldsInIndices')
            ->with(static::callback(static function (array $fields) {
                return \count($fields) === 1 && isset($fields['valid_field']);
            }));

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testSortingWithNonArrayJsonFromDatabaseIsSkipped(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                '"just a string"',
                '42',
                'null',
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }

    public function testSortingFieldsWithoutFieldKeyAreSkipped(): void
    {
        $elasticsearchHelper = static::createStub(ElasticsearchHelper::class);
        $elasticsearchHelper
            ->method('allowIndexing')
            ->willReturn(true);

        $connection = $this->createMock(Connection::class);
        $connection
            ->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['order' => 'asc', 'priority' => 1],
                    ['field' => 123, 'order' => 'asc', 'priority' => 2],
                    ['field' => 'blog.name', 'order' => 'asc', 'priority' => 3],
                ]),
            ]);

        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->once())->method('createFieldsInIndices')->with([]);

        $updater = new BlogCustomFieldsUsedUpdater(
            $elasticsearchHelper,
            $mappingHelper,
            $connection
        );

        $writeResults = [
        ];

        $event = new EntityWrittenEvent(BlogSortingDefinition::ENTITY_NAME, $writeResults, Context::createDefaultContext());

        $updater->blogSortingWritten($event);
    }
}
