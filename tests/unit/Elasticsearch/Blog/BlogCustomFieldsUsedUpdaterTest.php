<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Blog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
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
        ], BlogCustomFieldsUsedUpdater::getSubscribedEvents());
    }

    public function testBlogSortingDoesNothingWhenElasticsearchIsDisabled(): void
    {
        $helper = static::createStub(ElasticsearchHelper::class);
        $helper->method('allowIndexing')->willReturn(false);
        $mappingHelper = $this->createMock(ElasticsearchCustomFieldsMappingHelper::class);
        $mappingHelper->expects($this->never())->method('createFieldsInIndices');

        $updater = new BlogCustomFieldsUsedUpdater(
            $helper,
            $mappingHelper,
            static::createStub(Connection::class),
        );

        $updater->blogSortingWritten(new EntityWrittenEvent(
            BlogSortingDefinition::ENTITY_NAME,
            [
                new EntityWriteResult(
                    'sorting-id',
                    ['fields' => [['field' => 'customFields.editorial_priority']]],
                    BlogSortingDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_INSERT,
                ),
            ],
            Context::createDefaultContext(),
        ));
    }

    /**
     * @param list<string> $rows
     * @param list<string> $expected
     */
    #[DataProvider('customFieldRowsProvider')]
    public function testExtractCustomFieldNames(array $rows, array $expected): void
    {
        static::assertSame($expected, BlogCustomFieldsUsedUpdater::extractCustomFieldNames($rows));
    }

    public static function customFieldRowsProvider(): \Generator
    {
        yield 'extracts and deduplicates custom fields' => [
            [
                '[{"field":"customFields.editorial_priority"},{"field":"name"}]',
                '[{"field":"customFields.editorial_priority"},{"field":"customFields.reading_time"}]',
            ],
            ['editorial_priority', 'reading_time'],
        ];

        yield 'ignores invalid JSON' => [
            ['not-json'],
            [],
        ];

        yield 'ignores non-list JSON' => [
            ['"customFields.editorial_priority"'],
            [],
        ];

        yield 'ignores fields without a custom field name' => [
            ['[{"field":"name"},{"priority":1},{"field":null}]'],
            [],
        ];
    }
}
