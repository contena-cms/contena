<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearchDSL\Aggregation\Bucketing\CompositeAggregation;
use OpenSearchDSL\Sort\FieldSort;
use OpenSearchDSL\Sort\NestedSort;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTranslation\BlogTranslationDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Contena\Core\Framework\DataAbstractionLayer\Field\IntField;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\System\CustomField\CustomFieldService;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Contena\Elasticsearch\ElasticsearchException;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(CriteriaParser::class)]
class CriteriaParserTest extends TestCase
{
    private const SECOND_LANGUAGE = 'd5da80fc94874ea988eac8abdea44e0a';

    public function testAggregationWithSorting(): void
    {
        $aggs = new TermsAggregation('foo', 'test', null, new FieldSorting('abc', FieldSorting::ASCENDING), new TermsAggregation('foo', 'foo2'));

        $definition = $this->getDefinition();

        /** @var CompositeAggregation $esAgg */
        $esAgg = new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            static::createStub(CustomFieldService::class),
        )->parseAggregation($aggs, $definition, Context::createDefaultContext());

        static::assertInstanceOf(CompositeAggregation::class, $esAgg);
        static::assertSame([
            'composite' => [
                'sources' => [
                    [
                        'foo.sorting' => [
                            'terms' => [
                                'field' => 'abc',
                                'order' => 'ASC',
                            ],
                        ],
                    ],
                    [
                        'foo.key' => [
                            'terms' => [
                                'field' => 'test',
                            ],
                        ],
                    ],
                ],
                'size' => 10000,
            ],
            'aggregations' => [
                'foo' => [
                    'terms' => [
                        'field' => 'foo2',
                        'size' => 10000,
                    ],
                ],
            ],
        ], $esAgg->toArray());
    }

    public function testParseAggregationWithTranslatedField(): void
    {
        $aggs = new TermsAggregation('byName', 'name');

        $definition = $this->getDefinition();

        $parser = new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            static::createStub(CustomFieldService::class),
        );

        $esAgg = $parser->parseAggregation($aggs, $definition, Context::createDefaultContext());

        static::assertInstanceOf(\OpenSearchDSL\Aggregation\Bucketing\TermsAggregation::class, $esAgg);
        static::assertSame([
            'terms' => [
                'field' => 'name.' . Defaults::LANGUAGE_SYSTEM,
                'size' => 10000,
            ],
        ], $esAgg->toArray());
    }

    /**
     * @param array<mixed> $expectedEsStats
     */
    #[DataProvider('parseStatsDataProvider')]
    public function testParseStatsAggregation(string $fieldName, array $expectedEsStats): void
    {
        $aggs = new StatsAggregation('fooStats', $fieldName);

        $definition = $this->getDefinition();

        $parser = new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            static::createStub(CustomFieldService::class),
        );

        $esAgg = $parser->parseAggregation($aggs, $definition, Context::createDefaultContext());

        static::assertInstanceOf(\OpenSearchDSL\Aggregation\Metric\StatsAggregation::class, $esAgg);
        static::assertSame($expectedEsStats, $esAgg->toArray());
    }

    /**
     * @param array<mixed> $expectedEsFilter
     */
    #[DataProvider('parseFilterDataProvider')]
    public function testParseFilter(Filter $filter, array $expectedEsFilter): void
    {
        $definition = $this->getDefinition();

        $parser = new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            static::createStub(CustomFieldService::class),
        );

        $context = new Context(
            new SystemSource(),
            [self::SECOND_LANGUAGE, Defaults::LANGUAGE_SYSTEM],
        );

        $esFilter = $parser->parseFilter($filter, $definition, BlogDefinition::ENTITY_NAME, $context);
        static::assertSame($expectedEsFilter, $esFilter->toArray());
    }

    public function testParseUnsupportedFilter(): void
    {
        $definition = $this->getDefinition();

        $parser = new CriteriaParser(new EntityDefinitionQueryHelper(), static::createStub(CustomFieldService::class));

        $this->expectExceptionObject(ElasticsearchException::unsupportedFilter(CustomFilter::class));
        $parser->parseFilter(new CustomFilter(), $definition, BlogDefinition::ENTITY_NAME, Context::createDefaultContext());
    }

    #[DataProvider('accessorContextProvider')]
    public function testBuildAccessor(string $field, Context $context, string $expectedAccessor): void
    {
        $definition = $this->getDefinition();

        $accessor = new CriteriaParser(new EntityDefinitionQueryHelper(), static::createStub(CustomFieldService::class))->buildAccessor($definition, $field, $context);

        static::assertSame($expectedAccessor, $accessor);
    }

    /**
     * @return iterable<string, array{string, array<mixed>}>
     */
    public static function parseStatsDataProvider(): iterable
    {
        yield 'other field stats aggregation' => [
            'name',
            [
                'stats' => [
                    'field' => 'name.2fbb5fe2e29a4d70aa5854ce7ce3e20b',
                ],
            ],
        ];
    }

    /**
     * @return iterable<string, Filter|array<mixed>>
     */
    public static function parseFilterDataProvider(): iterable
    {
        $now = '2023-06-12 05:36:22.000';

        yield 'NotFilter field' => [
            new NotFilter('AND', [
                new EqualsFilter('id', 'foo'),
                new EqualsFilter('coverId', 'bar'),
            ]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            'id' => 'foo',
                                        ],
                                    ],
                                    [
                                        'term' => [
                                            'coverId' => 'bar',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'NotFilter translated field' => [
            new NotFilter('AND', [
                new EqualsFilter('name', 'foo'),
                new EqualsFilter('description', 'bar'),
            ]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            'name.' . self::SECOND_LANGUAGE => 'foo',
                                        ],
                                    ],
                                    [
                                        'multi_match' => [
                                            'query' => 'bar',
                                            'fields' => [
                                                'description.' . self::SECOND_LANGUAGE,
                                                'description.' . Defaults::LANGUAGE_SYSTEM,
                                            ],
                                            'type' => 'best_fields',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'MultiFilter field' => [
            new MultiFilter('AND', [
                new EqualsFilter('id', 'foo'),
                new EqualsFilter('coverId', 'bar'),
            ]),
            [
                'bool' => [
                    'must' => [
                        [
                            'term' => [
                                'id' => 'foo',
                            ],
                        ],
                        [
                            'term' => [
                                'coverId' => 'bar',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'MultiFilter translated field' => [
            new MultiFilter('AND', [
                new EqualsFilter('name', 'foo'),
                new EqualsFilter('description', 'bar'),
            ]),
            [
                'bool' => [
                    'must' => [
                        [
                            'term' => [
                                'name.' . self::SECOND_LANGUAGE => 'foo',
                            ],
                        ],
                        [
                            'multi_match' => [
                                'query' => 'bar',
                                'fields' => [
                                    'description.' . self::SECOND_LANGUAGE,
                                    'description.' . Defaults::LANGUAGE_SYSTEM,
                                ],
                                'type' => 'best_fields',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'EqualsFilter field' => [
            new EqualsFilter('coverId', 'bar'),
            [
                'term' => [
                    'coverId' => 'bar',
                ],
            ],
        ];
        yield 'EqualsFilter name translated field' => [
            new EqualsFilter('name', 'foo'),
            [
                'term' => [
                    'name.' . self::SECOND_LANGUAGE => 'foo',
                ],
            ],
        ];

        yield 'EqualsFilter description translated field' => [
            new EqualsFilter('description', 'foo'),
            [
                'multi_match' => [
                    'query' => 'foo',
                    'fields' => [
                        'description.' . self::SECOND_LANGUAGE,
                        'description.' . Defaults::LANGUAGE_SYSTEM,
                    ],
                    'type' => 'best_fields',
                ],
            ],
        ];

        yield 'EqualsAnyFilter field' => [
            new EqualsAnyFilter('coverId', ['foo', 'bar']),
            [
                'terms' => [
                    'coverId' => ['foo', 'bar'],
                ],
            ],
        ];

        yield 'EqualsAnyFilter name translated field' => [
            new EqualsAnyFilter('name', ['foo', 'bar']),
            [
                'terms' => [
                    'name.' . self::SECOND_LANGUAGE => ['foo', 'bar'],
                ],
            ],
        ];

        yield 'EqualsAnyFilter description translated field' => [
            new EqualsAnyFilter('description', ['foo', 'bar']),
            [
                'dis_max' => [
                    'queries' => [
                        [
                            'terms' => [
                                'description.' . self::SECOND_LANGUAGE => ['foo', 'bar'],
                            ],
                        ],
                        [
                            'terms' => [
                                'description.' . Defaults::LANGUAGE_SYSTEM => ['foo', 'bar'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'EqualsAnyFilter field with null' => [
            new EqualsAnyFilter('coverId', ['foo', 'bar', null]),
            [
                'bool' => [
                    'should' => [
                        [
                            'terms' => [
                                'coverId' => ['foo', 'bar'],
                            ],
                        ],
                        [
                            'bool' => [
                                'must_not' => [
                                    [
                                        'exists' => [
                                            'field' => 'coverId',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'EqualsAnyFilter name translated field with null' => [
            new EqualsAnyFilter('name', ['foo', 'bar', null]),
            [
                'bool' => [
                    'should' => [
                        [
                            'terms' => [
                                'name.' . self::SECOND_LANGUAGE => ['foo', 'bar'],
                            ],
                        ],
                        [
                            'bool' => [
                                'must_not' => [
                                    [
                                        'exists' => [
                                            'field' => 'name.' . self::SECOND_LANGUAGE,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'ContainsFilter field' => [
            new ContainsFilter('coverId', 'foo'),
            [
                'wildcard' => [
                    'coverId' => [
                        'value' => '*foo*',
                    ],
                ],
            ],
        ];
        yield 'ContainsFilter name translated field' => [
            new ContainsFilter('name', 'foo'),
            [
                'wildcard' => [
                    'name.' . self::SECOND_LANGUAGE => [
                        'value' => '*foo*',
                    ],
                ],
            ],
        ];
        yield 'PrefixFilter field' => [
            new PrefixFilter('coverId', 'foo'),
            [
                'prefix' => [
                    'coverId' => [
                        'value' => 'foo',
                    ],
                ],
            ],
        ];
        yield 'PrefixFilter name translated field' => [
            new PrefixFilter('name', 'foo'),
            [
                'prefix' => [
                    'name.' . self::SECOND_LANGUAGE => [
                        'value' => 'foo',
                    ],
                ],
            ],
        ];
        yield 'SuffixFilter field' => [
            new SuffixFilter('coverId', 'foo'),
            [
                'wildcard' => [
                    'coverId' => [
                        'value' => '*foo',
                    ],
                ],
            ],
        ];
        yield 'SuffixFilter name translated field' => [
            new SuffixFilter('name', 'foo'),
            [
                'wildcard' => [
                    'name.' . self::SECOND_LANGUAGE => [
                        'value' => '*foo',
                    ],
                ],
            ],
        ];
        yield 'RangeFilter field' => [
            new RangeFilter('createdAt', [
                RangeFilter::GT => $now,
            ]),
            [
                'range' => [
                    'createdAt' => [
                        RangeFilter::GT => $now,
                    ],
                ],
            ],
        ];
        yield 'RangeFilter name translated field' => [
            new RangeFilter('name', [
                RangeFilter::GT => $now,
            ]),
            [
                'range' => [
                    'name.' . self::SECOND_LANGUAGE => [
                        RangeFilter::GT => $now,
                    ],
                ],
            ],
        ];

        yield 'EqualsFilter translated custom field' => [
            new EqualsFilter('customFields.foo', null),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'exists' => ['field' => 'customFields.' . self::SECOND_LANGUAGE . '.foo'],
                        ],
                        [
                            'exists' => ['field' => 'customFields.' . Defaults::LANGUAGE_SYSTEM . '.foo'],
                        ],
                    ],
                ],
            ],
        ];

        yield 'MultiFilter with translated custom field' => [
            new MultiFilter('AND', [
                new EqualsFilter('customFields.foo', 'fooValue'),
                new EqualsFilter('customFields.bar', 'barValue'),
            ]),
            [
                'bool' => [
                    'must' => [
                        [
                            'multi_match' => [
                                'query' => 'fooValue',
                                'fields' => [
                                    'customFields.' . self::SECOND_LANGUAGE . '.foo',
                                    'customFields.' . Defaults::LANGUAGE_SYSTEM . '.foo',
                                ],
                                'type' => 'best_fields',
                            ],
                        ],
                        [
                            'multi_match' => [
                                'query' => 'barValue',
                                'fields' => [
                                    'customFields.' . self::SECOND_LANGUAGE . '.bar',
                                    'customFields.' . Defaults::LANGUAGE_SYSTEM . '.bar',
                                ],
                                'type' => 'best_fields',
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @return iterable<string, array{string, Context, string}>
     */
    public static function accessorContextProvider(): iterable
    {
        yield 'normal field' => [
            'foo',
            Context::createDefaultContext(),
            'foo',
        ];
    }

    #[DataProvider('providerTranslatedField')]
    public function testTranslatedFieldSorting(FieldSorting $sorting, FieldSort $expectedFieldSort, ?Field $customField = null): void
    {
        $definition = $this->getDefinition(CategoryDefinition::ENTITY_NAME);

        $customFieldService = static::createStub(CustomFieldService::class);

        if ($customField instanceof Field) {
            $customFieldService->method('getCustomField')->willReturn($customField);
        }

        $context = Context::createDefaultContext();
        $context->assign([
            'languageIdChain' => [
                Defaults::LANGUAGE_SYSTEM,
                self::SECOND_LANGUAGE,
            ],
        ]);

        $fieldSort = new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            $customFieldService,
        )->parseSorting($sorting, $definition, $context);

        static::assertSame($expectedFieldSort->getField(), $fieldSort->getField());
        static::assertNotNull($expectedFieldSort->getOrder());
        static::assertNotNull($fieldSort->getOrder());
        static::assertSame(strtolower($expectedFieldSort->getOrder()), strtolower($fieldSort->getOrder()));
        static::assertSame($expectedFieldSort->getParameters(), $fieldSort->getParameters());
    }

    /**
     * @return iterable<string, array{FieldSorting, FieldSort, ?Field}>
     */
    public static function providerTranslatedField(): iterable
    {
        yield 'non translated field' => [
            new FieldSorting('coverId', FieldSorting::ASCENDING),
            new FieldSort('coverId', FieldSorting::ASCENDING, null, []),
            null,
        ];

        yield 'customFields translated field' => [
            new FieldSorting('customFields.foo', FieldSorting::DESCENDING),
            new FieldSort('_script', FieldSorting::DESCENDING, null, [
                'type' => 'string',
                'script' => [
                    'source' => static::getScriptStringSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'customFields',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                        'suffix' => 'foo',
                    ],
                ],
            ]),
            new StringField('foo', 'foo'),
        ];

        yield 'customFields int translated field' => [
            new FieldSorting('customFields.foo', FieldSorting::ASCENDING),
            new FieldSort('_script', FieldSorting::ASCENDING, null, [
                'type' => 'number',
                'script' => [
                    'source' => static::getScriptIntSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'customFields',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                        'suffix' => 'foo',
                        'order' => FieldSort::ASC,
                    ],
                ],
            ]),
            new IntField('foo', 'foo'),
        ];

        yield 'customFields float translated field' => [
            new FieldSorting('customFields.foo', FieldSorting::ASCENDING),
            new FieldSort('_script', FieldSort::ASC, null, [
                'type' => 'number',
                'script' => [
                    'source' => static::getScriptIntSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'customFields',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                        'suffix' => 'foo',
                        'order' => FieldSort::ASC,
                    ],
                ],
            ]),
            new FloatField('foo', 'foo'),
        ];

        yield 'non nested translated field' => [
            new FieldSorting('name', FieldSorting::ASCENDING),
            new FieldSort('_script', FieldSort::ASC, null, [
                'type' => 'string',
                'script' => [
                    'source' => static::getScriptStringSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'name',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                    ],
                ],
            ]),
            null,
        ];

        yield 'non translated field with root prefix' => [
            new FieldSorting('category.name', FieldSorting::ASCENDING),
            new FieldSort('_script', FieldSort::ASC, null, [
                'type' => 'string',
                'script' => [
                    'source' => static::getScriptStringSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'name',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                    ],
                ],
            ]),
            null,
        ];

        yield 'nested translated field' => [
            new FieldSorting('category.children.name', FieldSorting::ASCENDING),
            new FieldSort('_script', FieldSort::ASC, new NestedSort('children'), [
                'type' => 'string',
                'script' => [
                    'source' => static::getScriptStringSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'children.name',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                    ],
                ],
            ]),
            null,
        ];

        yield 'nested translated field with root prefix' => [
            new FieldSorting('children.name', FieldSorting::ASCENDING),
            new FieldSort('_script', FieldSort::ASC, new NestedSort('children'), [
                'type' => 'string',
                'script' => [
                    'source' => static::getScriptStringSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'children.name',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                    ],
                ],
            ]),
            null,
        ];

        yield 'customFields string translated field in descending order' => [
            new FieldSorting('customFields.bar', FieldSorting::DESCENDING),
            new FieldSort('_script', FieldSort::DESC, null, [
                'type' => 'string',
                'script' => [
                    'source' => static::getScriptStringSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'customFields',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                        'suffix' => 'bar',
                    ],
                ],
            ]),
            new StringField('bar', 'bar'),
        ];

        yield 'customFields bool translated field' => [
            new FieldSorting('customFields.boolField', FieldSorting::DESCENDING),
            new FieldSort('_script', FieldSort::DESC, null, [
                'type' => 'string',
                'script' => [
                    'source' => static::getScriptStringSortingSource(),
                    'lang' => 'painless',
                    'params' => [
                        'field' => 'customFields',
                        'languages' => [
                            Defaults::LANGUAGE_SYSTEM,
                            self::SECOND_LANGUAGE,
                        ],
                        'suffix' => 'boolField',
                    ],
                ],
            ]),
            new BoolField('boolField', 'boolField'),
        ];
    }

    /**
     * @param array<mixed> $expectedFilter
     */
    #[DataProvider('providerFilter')]
    public function testFilterParsing(Filter $filter, array $expectedFilter): void
    {
        $context = Context::createDefaultContext();
        $definition = $this->getDefinition();

        $sortedFilter = new CriteriaParser(
            new EntityDefinitionQueryHelper(),
            static::createStub(CustomFieldService::class),
        )->parseFilter($filter, $definition, '', $context);

        $sortedFilterArray = $sortedFilter->toArray();

        // Unset the 'source' key before comparison.
        unset($sortedFilterArray['script']['script']['source']);

        static::assertEquals($expectedFilter, $sortedFilterArray);
    }

    /**
     * @return iterable<string, array{Filter, array<mixed>}>
     */
    public static function providerFilter(): iterable
    {
        yield 'not filter: and' => [
            new NotFilter(MultiFilter::CONNECTION_AND, [new EqualsFilter('test', 'value'), new EqualsFilter('test2', 'value')]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'must' => [
                                    [
                                        'term' => [
                                            'test' => 'value',
                                        ],
                                    ],
                                    [
                                        'term' => [
                                            'test2' => 'value',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'not filter: or' => [
            new NotFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('test', 'value'), new EqualsFilter('test2', 'value')]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'should' => [
                                    [
                                        'term' => [
                                            'test' => 'value',
                                        ],
                                    ],
                                    [
                                        'term' => [
                                            'test2' => 'value',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'not filter: xor' => [
            new NotFilter(MultiFilter::CONNECTION_XOR, [new EqualsFilter('test', 'value'), new EqualsFilter('test2', 'value')]),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'bool' => [
                                'should' => [
                                    [
                                        'bool' => [
                                            'must' => [
                                                [
                                                    'term' => [
                                                        'test' => 'value',
                                                    ],
                                                ],
                                            ],
                                            'must_not' => [
                                                [
                                                    'term' => [
                                                        'test2' => 'value',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                    [
                                        'bool' => [
                                            'must_not' => [
                                                [
                                                    'term' => [
                                                        'test' => 'value',
                                                    ],
                                                ],
                                            ],
                                            'must' => [
                                                [
                                                    'term' => [
                                                        'test2' => 'value',
                                                    ],
                                                ],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'range filter: datetime' => [
            new RangeFilter('createdAt', [RangeFilter::GTE => '2023-06-01', RangeFilter::LT => '2023-06-03 13:47:42.759']),
            [
                'range' => [
                    'createdAt' => [
                        'gte' => '2023-06-01 00:00:00.000',
                        'lt' => '2023-06-03 13:47:42.000',
                    ],
                ],
            ],
        ];

        yield 'translated property of related entity' => [
            new EqualsFilter('categories.name', 'value'),
            [
                'nested' => [
                    'path' => 'categories',
                    'query' => [
                        'term' => [
                            'categories.name.2fbb5fe2e29a4d70aa5854ce7ce3e20b' => 'value',
                        ],
                    ],
                ],
            ],
        ];

        yield 'EqualsFilter null on nested association field' => [
            new EqualsFilter('categories.id', null),
            [
                'bool' => [
                    'must_not' => [
                        [
                            'nested' => [
                                'path' => 'categories',
                                'query' => [
                                    'exists' => ['field' => 'categories.id'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public function getDefinition(string $entityName = 'blog'): EntityDefinition
    {
        $instanceRegistry = new StaticDefinitionInstanceRegistry(
            [
                BlogDefinition::class,
                BlogCategoryDefinition::class,
                CategoryDefinition::class,
                CategoryTranslationDefinition::class,
                BlogTranslationDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        return $instanceRegistry->getByEntityName($entityName);
    }

    public static function getScriptStringSortingSource(): string
    {
        return 'def languages = params[\'languages\'];
def suffix = params.containsKey(\'suffix\') ? \'.\' + params[\'suffix\'] : \'\';

for (int i = 0; i < languages.length; i++) {
    def field_name = params[\'field\'] + \'.\' + languages[i] + suffix;

    if (doc[field_name].size() > 0 && doc[field_name].value != null && doc[field_name].value.toString().length() > 0) {
        def fieldValue = doc[field_name].value;

        return fieldValue.toString();
    }
}

return \'\';
';
    }

    public static function getScriptIntSortingSource(): string
    {
        return 'def languages = params[\'languages\'];
def suffix = params.containsKey(\'suffix\') ? \'.\' + params[\'suffix\'] : \'\';

for (int i = 0; i < languages.length; i++) {
    def field_name = params[\'field\'] + \'.\' + languages[i] + suffix;

    if (doc[field_name].size() > 0 && doc[field_name].value != null && doc[field_name].value.toString().length() > 0) {
        def fieldValue = doc[field_name].value;

        return fieldValue;
    }
}

if (params[\'order\'] == \'asc\') {
    return Double.MAX_VALUE;
}

return Double.MIN_VALUE;
';
    }
}

/**
 * @internal
 */
class CustomFilter extends Filter
{
    public function getFields(): array
    {
        return [];
    }
}
