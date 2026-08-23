<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Elasticsearch\Event\ElasticsearchCustomFieldsMappingEvent;
use Contena\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ElasticsearchIndexingUtils::class)]
class ElasticsearchIndexingUtilsTest extends TestCase
{
    public function testGetCustomFieldTypes(): void
    {
        $dispatcher = new EventDispatcher();

        $customFieldsMappingEventDispatched = 0;

        $dispatcher->addListener(ElasticsearchCustomFieldsMappingEvent::class, static function (ElasticsearchCustomFieldsMappingEvent $event) use (&$customFieldsMappingEventDispatched): void {
            ++$customFieldsMappingEventDispatched;
        });

        $parameterBag = new ParameterBag(['elasticsearch.blog.custom_fields_mapping' => [
            'cf_foo' => 'text',
            'cf_baz' => 'int',
        ]]);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([
                'cf_bool' => 'bool',
            ]);

        $utils = new ElasticsearchIndexingUtils(
            $connection,
            $dispatcher,
            $parameterBag,
        );

        // run twice to make sure memoize works
        $formatted = $utils->getCustomFieldTypes(BlogDefinition::ENTITY_NAME, new Context(new SystemSource()));
        $utils->getCustomFieldTypes(BlogDefinition::ENTITY_NAME, new Context(new SystemSource()));

        static::assertSame([
            'cf_bool' => 'bool',
            'cf_foo' => 'text',
            'cf_baz' => 'int',
        ], $formatted);
    }

    public function testGetCustomFieldTypesOnlyReturnsSearchableFields(): void
    {
        $dispatcher = new EventDispatcher();
        $parameterBag = new ParameterBag([]);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with(
                static::callback(static function (string $sql): bool {
                    return str_contains($sql, 'custom_field.include_in_search = 1')
                        && str_contains($sql, 'custom_field.active = 1');
                }),
                static::anything(),
                static::anything()
            )
            ->willReturn([
                'searchable_field' => 'text',
            ]);

        $utils = new ElasticsearchIndexingUtils(
            $connection,
            $dispatcher,
            $parameterBag,
        );

        $result = $utils->getCustomFieldTypes(BlogDefinition::ENTITY_NAME, new Context(new SystemSource()));

        static::assertSame([
            'searchable_field' => 'text',
        ], $result);
    }

    public function testGetCustomFieldTypesIncludesFieldsUsedInSorting(): void
    {
        $dispatcher = new EventDispatcher();
        $parameterBag = new ParameterBag([]);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([json_encode([['field' => 'customFields.sorting_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false]])]);

        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with(
                static::callback(static function (string $sql): bool {
                    return str_contains($sql, 'custom_field.name IN (:fields)');
                }),
                static::callback(static function (array $params): bool {
                    return $params['fields'] === ['sorting_field'];
                }),
                static::callback(static function (array $types): bool {
                    return isset($types['fields']) && $types['fields'] === ArrayParameterType::STRING;
                })
            )
            ->willReturn([
                'sorting_field' => 'int',
            ]);

        $utils = new ElasticsearchIndexingUtils(
            $connection,
            $dispatcher,
            $parameterBag,
        );

        $result = $utils->getCustomFieldTypes(BlogDefinition::ENTITY_NAME, new Context(new SystemSource()));

        static::assertSame([
            'sorting_field' => 'int',
        ], $result);
    }

    public function testStripText(): void
    {
        $input1 = '<p>This is <b>bold</b> text.</p>';
        $expected1 = 'This is bold text.';
        $result1 = ElasticsearchIndexingUtils::stripText($input1);
        static::assertSame($expected1, $result1);

        $input2 = 'This is a short text.';
        $result2 = ElasticsearchIndexingUtils::stripText($input2);
        static::assertSame($input2, $result2);

        $input3 = str_repeat('a', 32766);
        $result3 = ElasticsearchIndexingUtils::stripText($input3);
        static::assertSame($input3, $result3);

        $input4 = str_repeat('a', 33000);
        $expected4 = mb_substr($input4, 0, 32766);
        $result4 = ElasticsearchIndexingUtils::stripText($input4);
        static::assertSame($expected4, $result4);
    }

    public function testParseJsonWithValidJson(): void
    {
        $record = [
            'data' => '{"key": "value"}', // Valid JSON string
        ];
        $field = 'data';

        $result = ElasticsearchIndexingUtils::parseJson($record, $field);

        static::assertSame(['key' => 'value'], $result);
    }

    public function testParseJsonWithNonExistField(): void
    {
        $record = [];
        $field = 'data';

        $result = ElasticsearchIndexingUtils::parseJson($record, $field);

        static::assertSame([], $result);
    }

    public function testParseJsonWithInvalidJson(): void
    {
        $record = [
            'data' => 'invalid-json', // Invalid JSON string
        ];
        $field = 'data';

        static::expectException(\JsonException::class);

        ElasticsearchIndexingUtils::parseJson($record, $field);
    }

    public function testExtractCustomFieldNamesSkipsInvalidJson(): void
    {
        $dispatcher = new EventDispatcher();
        $parameterBag = new ParameterBag([]);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                'not-valid-json{{{',
                json_encode([
                    ['field' => 'customFields.valid_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ]),
            ]);

        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with(
                static::callback(static function (string $sql): bool {
                    return str_contains($sql, 'custom_field.name IN (:fields)');
                }),
                static::callback(static function (array $params): bool {
                    return \in_array('valid_field', $params['fields'], true)
                        && \count($params['fields']) === 1;
                }),
                static::anything()
            )
            ->willReturn([
                'valid_field' => 'int',
            ]);

        $utils = new ElasticsearchIndexingUtils(
            $connection,
            $dispatcher,
            $parameterBag,
        );

        $result = $utils->getCustomFieldTypes(BlogDefinition::ENTITY_NAME, new Context(new SystemSource()));

        static::assertSame([
            'valid_field' => 'int',
        ], $result);
    }

    public function testExtractCustomFieldNamesSkipsNonArrayJson(): void
    {
        $dispatcher = new EventDispatcher();
        $parameterBag = new ParameterBag([]);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                '"just a string"',
                '42',
                'null',
            ]);

        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([]);

        $utils = new ElasticsearchIndexingUtils(
            $connection,
            $dispatcher,
            $parameterBag,
        );

        $result = $utils->getCustomFieldTypes(BlogDefinition::ENTITY_NAME, new Context(new SystemSource()));

        static::assertSame([], $result);
    }

    public function testExtractCustomFieldNamesDeduplicatesAcrossSortings(): void
    {
        $dispatcher = new EventDispatcher();
        $parameterBag = new ParameterBag([]);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['field' => 'customFields.shared_field', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ['field' => 'customFields.sorting_only', 'order' => 'asc', 'priority' => 2, 'naturalSorting' => false],
                ]),
                json_encode([
                    ['field' => 'customFields.shared_field', 'order' => 'desc', 'priority' => 1, 'naturalSorting' => true],
                ]),
            ]);

        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->with(
                static::anything(),
                static::callback(static function (array $params): bool {
                    $fields = $params['fields'];

                    return \count($fields) === 2
                        && \in_array('shared_field', $fields, true)
                        && \in_array('sorting_only', $fields, true);
                }),
                static::anything()
            )
            ->willReturn([
                'shared_field' => 'int',
                'sorting_only' => 'text',
            ]);

        $utils = new ElasticsearchIndexingUtils(
            $connection,
            $dispatcher,
            $parameterBag,
        );

        $result = $utils->getCustomFieldTypes(BlogDefinition::ENTITY_NAME, new Context(new SystemSource()));

        static::assertSame([
            'shared_field' => 'int',
            'sorting_only' => 'text',
        ], $result);
    }

    public function testExtractCustomFieldNamesIgnoresNonCustomFieldEntries(): void
    {
        $dispatcher = new EventDispatcher();
        $parameterBag = new ParameterBag([]);

        $connection = $this->createMock(Connection::class);

        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([
                json_encode([
                    ['field' => 'name', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                    ['field' => 'description', 'order' => 'asc', 'priority' => 2, 'naturalSorting' => false],
                ]),
            ]);

        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturn([]);

        $utils = new ElasticsearchIndexingUtils(
            $connection,
            $dispatcher,
            $parameterBag,
        );

        $result = $utils->getCustomFieldTypes(BlogDefinition::ENTITY_NAME, new Context(new SystemSource()));

        static::assertSame([], $result);
    }
}
