<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Framework\DataAbstractionLayer\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\WithoutErrorHandler;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCategory\BlogCategoryDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogMedia\BlogMediaDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogTag\BlogTagDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InvalidFilterQueryException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InvalidLimitQueryException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InvalidPageQueryException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InvalidSortQueryException;
use Contena\Core\Framework\DataAbstractionLayer\Exception\SearchRequestException;
use Contena\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\FrameworkException;
use Contena\Core\Framework\Util\Base64;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Tag\TagDefinition;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(RequestCriteriaBuilder::class)]
class RequestCriteriaBuilderTest extends TestCase
{
    private RequestCriteriaBuilder $requestCriteriaBuilder;

    private StaticDefinitionInstanceRegistry $staticDefinitionRegistry;

    protected function setUp(): void
    {
        $aggregationParser = new AggregationParser();

        $this->staticDefinitionRegistry = new StaticDefinitionInstanceRegistry(
            [
                new BlogDefinition(),
                new BlogTagDefinition(),
                new TagDefinition(),
                new BlogMediaDefinition(),
                new BlogCategoryDefinition(),
                new CategoryDefinition(),
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $this->requestCriteriaBuilder = new RequestCriteriaBuilder(
            $aggregationParser,
            new ApiCriteriaValidator($this->staticDefinitionRegistry),
            new CriteriaArrayConverter($aggregationParser),
            new CompressedCriteriaDecoder(),
        );
    }

    /**
     * @return iterable<string, array{int|null, int|null, int|null, bool}>
     */
    public static function maxApiLimitProvider(): iterable
    {
        yield 'Test null max limit' => [10000, null, 10000, false];
        yield 'Test null max limit and null limit' => [null, null, null, false];
        yield 'Test max limit with null limit' => [null, 100, 100, false];
        yield 'Test max limit with higher limit' => [200, 100, 100, true];
        yield 'Test max limit with lower limit' => [50, 100, 50, false];
    }

    #[DataProvider('maxApiLimitProvider')]
    public function testMaxApiLimit(?int $limit, ?int $max, ?int $expected, bool $exception = false): void
    {
        $body = ['limit' => $limit];

        $aggregationParser = new AggregationParser();

        $builder = new RequestCriteriaBuilder(
            $aggregationParser,
            new ApiCriteriaValidator($this->staticDefinitionRegistry),
            new CriteriaArrayConverter($aggregationParser),
            new CompressedCriteriaDecoder(),
            $max
        );

        $request = new Request([], $body);
        $request->setMethod(Request::METHOD_POST);

        try {
            $criteria = $builder->handleRequest($request, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
            static::assertSame($expected, $criteria->getLimit());
        } catch (SearchRequestException) {
            static::assertTrue($exception);
        }

        $request = new Request($body);
        $request->setMethod(Request::METHOD_GET);

        try {
            $criteria = $builder->handleRequest($request, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
            static::assertSame($expected, $criteria->getLimit());
        } catch (SearchRequestException) {
            static::assertTrue($exception);
        }
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function invalidCriteriaIdsProvider(): iterable
    {
        yield 'non string list' => [[123, 456]];
        yield 'non string key values' => [[[['foo'], ['bar']]]];
        yield 'non string values' => [[[['pk-1' => 123], ['pk-2' => 456]]]];
    }

    /**
     * @param array<mixed> $ids
     */
    #[DataProvider('invalidCriteriaIdsProvider')]
    public function testInvalidCriteriaIds(array $ids): void
    {
        $body = ['ids' => $ids];

        $request = new Request([], $body);
        $request->setMethod(Request::METHOD_POST);

        $postExceptionThrown = false;

        try {
            $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        } catch (DataAbstractionLayerException $e) {
            static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
            static::assertSame('FRAMEWORK__INVALID_API_CRITERIA_IDS', $e->getErrorCode());
            $postExceptionThrown = true;
        }

        $request = new Request($body);
        $request->setMethod(Request::METHOD_GET);

        $getExceptionThrown = false;

        try {
            $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        } catch (DataAbstractionLayerException $e) {
            static::assertSame(Response::HTTP_BAD_REQUEST, $e->getStatusCode());
            static::assertSame('FRAMEWORK__INVALID_API_CRITERIA_IDS', $e->getErrorCode());
            $getExceptionThrown = true;
        }

        static::assertTrue($postExceptionThrown);
        static::assertTrue($getExceptionThrown);
    }

    /**
     * @return iterable<string, array<mixed>>
     */
    public static function validCriteriaIdsProvider(): iterable
    {
        yield 'plain id list' => [['id1', 'id2'], ['id1', 'id2']];
        yield 'plain id' => ['id1', ['id1']];
        yield 'string concatenated id list' => ['id1|id2', ['id1', 'id2']];
        yield 'multiple pks' => [[['pk-1' => 'id1.1', 'pk-2' => 'id1.2'], ['pk-1' => 'id2.1', 'pk-2' => 'id2.2']], [['pk-1' => 'id1.1', 'pk-2' => 'id1.2'], ['pk-1' => 'id2.1', 'pk-2' => 'id2.2']]];
    }

    /**
     * @param string|array<mixed> $idPayload
     * @param array<string>|array<int, array<string>> $expectedIds
     */
    #[DataProvider('validCriteriaIdsProvider')]
    public function testValidCriteriaIds($idPayload, array $expectedIds): void
    {
        $body = ['ids' => $idPayload];

        $request = new Request([], $body);
        $request->setMethod(Request::METHOD_POST);

        $criteria = $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        static::assertSame($expectedIds, $criteria->getIds());

        $request = new Request($body);
        $request->setMethod(Request::METHOD_GET);

        $criteria = $this->requestCriteriaBuilder->handleRequest($request, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        static::assertSame($expectedIds, $criteria->getIds());
    }

    public function testAssociationsAddedToCriteria(): void
    {
        $body = [
            'limit' => 10,
            'page' => 1,
            'associations' => [
                'media' => [
                    'limit' => 25,
                    'page' => 1,
                    'filter' => [
                        ['type' => 'equals', 'field' => 'position', 'value' => 1],
                    ],
                    'sort' => [
                        ['field' => 'position'],
                    ],
                ],
            ],
        ];

        $request = new Request([], $body, [], [], []);
        $request->setMethod(Request::METHOD_POST);

        $criteria = new Criteria();
        $this->requestCriteriaBuilder->handleRequest(
            $request,
            $criteria,
            $this->staticDefinitionRegistry->get(BlogDefinition::class),
            Context::createDefaultContext()
        );

        static::assertTrue($criteria->hasAssociation('media'));
        $nested = $criteria->getAssociation('media');

        static::assertCount(1, $nested->getFilters());
        static::assertCount(1, $nested->getSorting());
    }

    public function testCriteriaToArray(): void
    {
        $criteria = new Criteria()
            ->addSorting(new FieldSorting('blog.createdAt', FieldSorting::DESCENDING))
            ->addSorting(new CountSorting('categories.id', CountSorting::ASCENDING))
            ->addAssociation('media.media')
            ->addAssociation('categories.parent')
            ->setLimit(1)
            ->setOffset((1 - 1) * 1)
            ->setTotalCountMode(100);

        $criteria->getAssociation('cover')->addSorting(new FieldSorting('createdAt', FieldSorting::DESCENDING));

        $criteriaArray = $this->requestCriteriaBuilder->toArray($criteria);

        $testArray = [
            'total-count-mode' => 100,
            'limit' => 1,
            'associations' => [
                'media' => [
                    'total-count-mode' => 0,
                    'associations' => [
                        'media' => [
                            'total-count-mode' => 0,
                        ],
                    ],
                ],
                'categories' => [
                    'total-count-mode' => 0,
                    'associations' => [
                        'parent' => [
                            'total-count-mode' => 0,
                        ],
                    ],
                ],
                'cover' => [
                    'total-count-mode' => 0,
                    'sort' => [
                        [
                            'field' => 'createdAt',
                            'naturalSorting' => false,
                            'extensions' => [],
                            'order' => 'DESC',
                        ],
                    ],
                ],
            ],
            'sort' => [
                [
                    'field' => 'blog.createdAt',
                    'naturalSorting' => false,
                    'extensions' => [],
                    'order' => 'DESC',
                ],
                [
                    'field' => 'categories.id',
                    'naturalSorting' => false,
                    'extensions' => [],
                    'order' => 'ASC',
                    'type' => 'count',
                ],
            ],
        ];

        static::assertEquals($testArray, $criteriaArray);
    }

    /**
     * @param array<string, mixed> $sortingPayload
     * @param list<FieldSorting> $expectedParsedSortings
     */
    #[DataProvider('sortingCaseProvider')]
    public function testSorting(array $sortingPayload, array $expectedParsedSortings): void
    {
        $request = new Request([], $sortingPayload, [], [], []);
        $request->setMethod(Request::METHOD_POST);

        $criteria = $this->requestCriteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->staticDefinitionRegistry->get(BlogDefinition::class),
            Context::createDefaultContext()
        );

        $sorting = $criteria->getSorting();
        static::assertCount(\count($expectedParsedSortings), $sorting);
        foreach ($expectedParsedSortings as $index => $expectedParsedSorting) {
            static::assertInstanceOf($expectedParsedSorting::class, $sorting[$index]);
            static::assertSame($expectedParsedSorting->getField(), $sorting[$index]->getField());
            static::assertSame($expectedParsedSorting->getDirection(), $sorting[$index]->getDirection());
            static::assertSame($expectedParsedSorting->getNaturalSorting(), $sorting[$index]->getNaturalSorting());
        }
    }

    public static function sortingCaseProvider(): \Generator
    {
        yield 'manual score sorting' => [
            [
                'sort' => [
                    [
                        'field' => '_score',
                    ],
                ],
            ],
            [
                new FieldSorting('_score'),
            ],
        ];

        yield 'multiple sortings' => [
            [
                'sort' => [
                    [
                        'field' => 'id',
                        'order' => 'ASC',
                        'naturalSorting' => true,
                    ],
                    [
                        'field' => 'releaseDate',
                        'order' => 'DESC',
                    ],
                ],
            ],
            [
                new FieldSorting('blog.id', FieldSorting::ASCENDING, true),
                new FieldSorting('blog.releaseDate', FieldSorting::DESCENDING),
            ],
        ];

        yield 'count sorting' => [
            [
                'sort' => [
                    [
                        'field' => 'id',
                        'type' => 'count',
                    ],
                ],
            ],
            [
                new CountSorting('blog.id'),
            ],
        ];

        yield 'simple sorting' => [
            [
                'sort' => 'id',
            ],
            [
                new FieldSorting('blog.id'),
            ],
        ];

        yield 'multiple simple sortings' => [
            [
                'sort' => 'id,-releaseDate',
            ],
            [
                new FieldSorting('blog.id'),
                new FieldSorting('blog.releaseDate', FieldSorting::DESCENDING),
            ],
        ];

        yield 'empty array sorting' => [
            [
                'sort' => [],
            ],
            [],
        ];

        yield 'invalid order option falls back to ascending' => [
            [
                'sort' => [
                    [
                        'field' => 'id',
                        'order' => 'invalid',
                    ],
                ],
            ],
            [
                new FieldSorting('blog.id'),
            ],
        ];

        yield 'true-ish naturals sort option' => [
            [
                'sort' => [
                    [
                        'field' => 'id',
                        'naturalSorting' => '1',
                    ],
                ],
            ],
            [
                new FieldSorting('blog.id', FieldSorting::ASCENDING, true),
            ],
        ];

        yield 'false-ish naturals sort option' => [
            [
                'sort' => [
                    [
                        'field' => 'id',
                        'naturalSorting' => '0',
                    ],
                ],
            ],
            [
                new FieldSorting('blog.id'),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $sortingPayload
     */
    #[DataProvider('invalidSortingCaseProvider')]
    public function testInvalidSorting(array $sortingPayload, InvalidSortQueryException $expected): void
    {
        $request = new Request([], $sortingPayload, [], [], []);
        $request->setMethod(Request::METHOD_POST);

        $wasThrown = false;

        try {
            $this->requestCriteriaBuilder->handleRequest(
                $request,
                new Criteria(),
                $this->staticDefinitionRegistry->get(BlogDefinition::class),
                Context::createDefaultContext()
            );
        } catch (SearchRequestException $e) {
            self::assertSingleInnerException($e, $expected, (string) $expected->getParameter('path'));

            $wasThrown = true;
        }

        static::assertTrue($wasThrown);
    }

    public static function invalidSortingCaseProvider(): \Generator
    {
        yield 'empty string sorting' => [
            [
                'sort' => '',
            ],
            DataAbstractionLayerException::invalidSortQuery('The "sort" parameter needs to be a sorting array or a comma separated list of fields', '/sort'),
        ];

        yield 'empty array sorting' => [
            [
                'sort' => [[]],
            ],
            DataAbstractionLayerException::invalidSortQuery('The "sort" array needs to be an associative array at least containing a field name', '/sort/0'),
        ];

        yield 'non nested array' => [
            [
                'sort' => ['id'],
            ],
            DataAbstractionLayerException::invalidSortQuery('The "sort" array needs to be an associative array at least containing a field name', '/sort/0'),
        ];

        yield 'field is not a string' => [
            [
                'sort' => [['field' => 1]],
            ],
            DataAbstractionLayerException::invalidSortQuery('The "sort" array needs to be an associative array at least containing a field name', '/sort/0'),
        ];

        yield 'array invalid second sorting' => [
            [
                'sort' => [['field' => 'id'], []],
            ],
            DataAbstractionLayerException::invalidSortQuery('The "sort" array needs to be an associative array at least containing a field name', '/sort/1'),
        ];
    }

    public function testMaxLimitForAssociations(): void
    {
        $aggregationParser = new AggregationParser();
        $builder = new RequestCriteriaBuilder(
            $aggregationParser,
            new ApiCriteriaValidator($this->staticDefinitionRegistry),
            new CriteriaArrayConverter($aggregationParser),
            new CompressedCriteriaDecoder(),
            100
        );

        $payload = [
            'associations' => [
                'tags' => ['limit' => 101],
                'media' => ['limit' => null],
                'categories' => [],
            ],
        ];

        $criteria = $builder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());

        static::assertTrue($criteria->hasAssociation('tags'));
        static::assertTrue($criteria->hasAssociation('categories'));

        static::assertSame(100, $criteria->getLimit());
        static::assertSame(101, $criteria->getAssociation('tags')->getLimit());
        static::assertNull($criteria->getAssociation('media')->getLimit());
        static::assertNull($criteria->getAssociation('categories')->getLimit());
    }

    public function testInvalidAssociations(): void
    {
        $payload = [
            'associations' => [
                1 => [],
            ],
        ];

        $this->expectExceptionObject(FrameworkException::associationNotFound('1'));

        $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
    }

    public function testInvalidAssociationsCriteria(): void
    {
        $payload = [
            'associations' => [
                'media' => 'invalid',
            ],
        ];

        $criteria = $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());

        static::assertEmpty($criteria->getAssociations());
    }

    #[DataProvider('providerTotalCount')]
    public function testDifferentTotalCount(mixed $totalCountMode, int $expectedMode): void
    {
        $payload = [
            'total-count-mode' => $totalCountMode,
        ];

        $criteria = $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        static::assertSame($expectedMode, $criteria->getTotalCountMode());
    }

    /**
     * @return iterable<array{string, int}>
     */
    public static function providerTotalCount(): iterable
    {
        yield [
            '0',
            Criteria::TOTAL_COUNT_MODE_NONE,
        ];

        yield [
            '1',
            Criteria::TOTAL_COUNT_MODE_EXACT,
        ];

        yield [
            '2',
            Criteria::TOTAL_COUNT_MODE_NEXT_PAGES,
        ];

        yield [
            '3',
            Criteria::TOTAL_COUNT_MODE_NONE,
        ];

        yield [
            '-3',
            Criteria::TOTAL_COUNT_MODE_NONE,
        ];

        yield [
            'none',
            Criteria::TOTAL_COUNT_MODE_NONE,
        ];

        yield [
            'none-2',
            Criteria::TOTAL_COUNT_MODE_NONE,
        ];

        yield [
            'exact',
            Criteria::TOTAL_COUNT_MODE_EXACT,
        ];

        yield [
            'next-pages',
            Criteria::TOTAL_COUNT_MODE_NEXT_PAGES,
        ];
    }

    /**
     * @param array<string, mixed> $pagingPayload
     */
    #[DataProvider('providerPaging')]
    public function testPaging(array $pagingPayload, ?int $expectedOffset, ?int $expectedLimit): void
    {
        $criteria = $this->requestCriteriaBuilder->fromArray($pagingPayload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        static::assertSame($expectedOffset, $criteria->getOffset());
        static::assertSame($expectedLimit, $criteria->getLimit());
    }

    public function testPageOffsetUsesFallbackLimitWhenRequestHasNoLimit(): void
    {
        $aggregationParser = new AggregationParser();
        $maxLimit = 500;

        $builder = new RequestCriteriaBuilder(
            $aggregationParser,
            new ApiCriteriaValidator($this->staticDefinitionRegistry),
            new CriteriaArrayConverter($aggregationParser),
            new CompressedCriteriaDecoder(),
            $maxLimit
        );

        $criteria = $builder->fromArray(
            ['page' => 2],
            new Criteria(),
            $this->staticDefinitionRegistry->get(BlogDefinition::class),
            Context::createDefaultContext()
        );

        static::assertSame($maxLimit, $criteria->getLimit());
        static::assertSame($maxLimit, $criteria->getOffset());
    }

    public static function providerPaging(): \Generator
    {
        yield 'offset correctly calculated' => [
            ['page' => 3, 'limit' => 10],
            20,
            10,
        ];

        yield 'no page' => [
            ['limit' => 10],
            null,
            10,
        ];

        yield 'no limit' => [
            ['page' => '3'],
            0,
            null,
        ];

        yield 'no paging info' => [
            [],
            null,
            null,
        ];
    }

    /**
     * @param array<string, mixed> $pagingPayload
     */
    #[DataProvider('providerInvalidPaging')]
    public function testInvalidPaging(array $pagingPayload, InvalidPageQueryException|InvalidLimitQueryException $expected, string $pointer): void
    {
        $wasThrown = false;

        try {
            $this->requestCriteriaBuilder->fromArray($pagingPayload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            self::assertSingleInnerException($e, $expected, $pointer);

            $wasThrown = true;
        }

        static::assertTrue($wasThrown);
    }

    public static function providerInvalidPaging(): \Generator
    {
        yield 'empty page' => [
            ['page' => '', 'limit' => 10],
            new InvalidPageQueryException('(empty)'),
            '/page',
        ];

        yield 'negative page' => [
            ['page' => '-3', 'limit' => 10],
            new InvalidPageQueryException(-3),
            '/page',
        ];

        yield 'page is string' => [
            ['page' => 'foo', 'limit' => 10],
            new InvalidPageQueryException('foo'),
            '/page',
        ];

        yield 'negative limit' => [
            ['page' => '3', 'limit' => '-10'],
            new InvalidLimitQueryException(-10),
            '/limit',
        ];

        yield 'empty limit' => [
            ['page' => '3', 'limit' => ''],
            new InvalidLimitQueryException('(empty)'),
            '/limit',
        ];

        yield 'limit is string' => [
            ['page' => '3', 'limit' => 'foo'],
            new InvalidLimitQueryException('foo'),
            '/limit',
        ];
    }

    public function testSimpleFilterAddsExceptionWithBlankKey(): void
    {
        $payload = [
            'filter' => [
                'name' => 'test',
                '' => 'test',
            ],
        ];

        $pointer = '/filter/1';
        $expected = DataAbstractionLayerException::invalidFilterQuery('The key for filter at position "1" must not be blank.', $pointer);
        static::assertInstanceOf(InvalidFilterQueryException::class, $expected);

        $this->expectExceptionObject(new SearchRequestException([$pointer => [$expected]]));

        try {
            $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            self::assertSingleInnerException($e, $expected, $pointer);

            throw $e;
        }
    }

    public function testSimpleFilterAddsExceptionWithBlankValue(): void
    {
        $field = 'name';
        $payload = [
            'filter' => [
                $field => '',
            ],
        ];

        $pointer = '/filter/' . $field;
        $expected = DataAbstractionLayerException::invalidFilterQuery(\sprintf('The value for filter "%s" must not be blank.', $field), $pointer);
        static::assertInstanceOf(InvalidFilterQueryException::class, $expected);

        $this->expectExceptionObject(new SearchRequestException([$pointer => [$expected]]));

        try {
            $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            self::assertSingleInnerException($e, $expected, $pointer);

            throw $e;
        }
    }

    public function testSimpleFilterAddsExceptionWithArrayInValue(): void
    {
        $field = 'name';
        $payload = [
            'filter' => [
                $field => ['test'],
            ],
        ];

        $pointer = '/filter/' . $field;
        $expected = DataAbstractionLayerException::invalidFilterQuery(\sprintf('The value for filter "%s" must be scalar.', $field), $pointer);
        static::assertInstanceOf(InvalidFilterQueryException::class, $expected);

        $this->expectExceptionObject(new SearchRequestException([$pointer => [$expected]]));

        try {
            $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            self::assertSingleInnerException($e, $expected, $pointer);

            throw $e;
        }
    }

    public function testFilterElementIsInvalid(): void
    {
        $payload = [
            'filter' => [
                0 => 'test',
            ],
        ];

        $pointer = '/filter/0';
        $expected = DataAbstractionLayerException::invalidFilterQuery('The filter parameter has to be an array.', $pointer);
        static::assertInstanceOf(InvalidFilterQueryException::class, $expected);

        $this->expectExceptionObject(new SearchRequestException([$pointer => [$expected]]));

        try {
            $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            self::assertSingleInnerException($e, $expected, $pointer);

            throw $e;
        }
    }

    public function testFilterElementIsNotArray(): void
    {
        $payload = [
            'filter' => 123,
        ];

        $pointer = '/filter';
        $expected = DataAbstractionLayerException::invalidFilterQuery('The filter parameter has to be a list of filters.', $pointer);
        static::assertInstanceOf(InvalidFilterQueryException::class, $expected);

        $this->expectExceptionObject(new SearchRequestException([$pointer => [$expected]]));

        try {
            $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            self::assertSingleInnerException($e, $expected, $pointer);

            throw $e;
        }
    }

    public function testSimplePostFilterAddsExceptionWithArrayInValue(): void
    {
        $field = 'name';
        $payload = [
            'post-filter' => [
                $field => ['test'],
            ],
        ];

        $pointer = '/post-filter/' . $field;
        $expected = DataAbstractionLayerException::invalidFilterQuery(\sprintf('The value for post-filter "%s" must be scalar.', $field), $pointer);
        static::assertInstanceOf(InvalidFilterQueryException::class, $expected);

        $this->expectExceptionObject(new SearchRequestException([$pointer => [$expected]]));

        try {
            $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            self::assertSingleInnerException($e, $expected, $pointer);

            throw $e;
        }
    }

    public function testPostFilterElementIsInvalid(): void
    {
        $payload = [
            'post-filter' => [
                0 => 'test',
            ],
        ];

        $pointer = '/post-filter/0';
        $expected = DataAbstractionLayerException::invalidFilterQuery('The post-filter parameter has to be an array.', $pointer);
        static::assertInstanceOf(InvalidFilterQueryException::class, $expected);

        $this->expectExceptionObject(new SearchRequestException([$pointer => [$expected]]));

        try {
            $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            self::assertSingleInnerException($e, $expected, $pointer);

            throw $e;
        }
    }

    public function testPostFilterElementIsNotArray(): void
    {
        $payload = [
            'post-filter' => 123,
        ];

        $pointer = '/post-filter';
        $expected = DataAbstractionLayerException::invalidFilterQuery('The post-filter parameter has to be a list of filters.', $pointer);
        static::assertInstanceOf(InvalidFilterQueryException::class, $expected);

        $this->expectExceptionObject(new SearchRequestException([$pointer => [$expected]]));

        try {
            $this->requestCriteriaBuilder->fromArray($payload, new Criteria(), $this->staticDefinitionRegistry->get(BlogDefinition::class), Context::createDefaultContext());
        } catch (SearchRequestException $e) {
            self::assertSingleInnerException($e, $expected, $pointer);

            throw $e;
        }
    }

    public function testIncludesArrayValidation(): void
    {
        $payload = [
            'includes' => ['blog', 'category'],
        ];

        $request = new Request([], $payload, [], [], []);
        $request->setMethod(Request::METHOD_POST);

        $criteria = new Criteria();

        $this->requestCriteriaBuilder->handleRequest(
            $request,
            $criteria,
            $this->staticDefinitionRegistry->get(BlogDefinition::class),
            Context::createDefaultContext()
        );

        $payload['includes'] = 'string_instead_of_array';

        $request = new Request(request: $payload);
        $request->setMethod(Request::METHOD_POST);

        $this->expectExceptionObject(DataAbstractionLayerException::expectedArrayWithType('includes', 'string'));

        $this->requestCriteriaBuilder->handleRequest(
            $request,
            $criteria,
            $this->staticDefinitionRegistry->get(BlogDefinition::class),
            Context::createDefaultContext()
        );
    }

    public function testExcludesArrayValidation(): void
    {
        $payload = [
            'excludes' => ['blog', 'category'],
        ];

        $request = new Request(request: $payload);
        $request->setMethod(Request::METHOD_POST);

        $criteria = new Criteria();

        $this->requestCriteriaBuilder->handleRequest(
            $request,
            $criteria,
            $this->staticDefinitionRegistry->get(BlogDefinition::class),
            Context::createDefaultContext()
        );

        $payload['excludes'] = 'string_instead_of_array';

        $request = new Request([], $payload, [], [], []);
        $request->setMethod(Request::METHOD_POST);

        $this->expectExceptionObject(DataAbstractionLayerException::expectedArrayWithType('excludes', 'string'));

        $this->requestCriteriaBuilder->handleRequest(
            $request,
            $criteria,
            $this->staticDefinitionRegistry->get(BlogDefinition::class),
            Context::createDefaultContext()
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('searchInfoHeaderProvider')]
    public function testIncludeSearchInfoHeader(array $data, bool $expectedState): void
    {
        $request = new Request();
        $request->setMethod(Request::METHOD_POST);

        if (isset($data['headerValue'])) {
            $request->headers->set(PlatformRequest::HEADER_INCLUDE_SEARCH_INFO, $data['headerValue']);
        }

        $criteria = $this->requestCriteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->staticDefinitionRegistry->get(BlogDefinition::class),
            Context::createDefaultContext()
        );

        static::assertSame($expectedState, $criteria->hasState(Criteria::STATE_DISABLE_SEARCH_INFO));
    }

    /**
     * @return iterable<string, array{data: array{headerValue?: string}, expectedState: bool}>
     */
    public static function searchInfoHeaderProvider(): iterable
    {
        yield 'no header set (default behavior)' => [
            'data' => [],
            'expectedState' => true,
        ];

        yield 'header set to 1 (enable search info)' => [
            'data' => ['headerValue' => '1'],
            'expectedState' => false,
        ];

        yield 'header set to 0 (disable search info)' => [
            'data' => ['headerValue' => '0'],
            'expectedState' => true,
        ];

        yield 'header set to other value (enable search info)' => [
            'data' => ['headerValue' => 'anything'],
            'expectedState' => false,
        ];
    }

    public function testCompressedCriteriaParameter(): void
    {
        $criteriaData = [
            'limit' => 25,
            'page' => 2,
            'filter' => [
                ['type' => 'equals', 'field' => 'active', 'value' => true],
            ],
            'sort' => [
                ['field' => 'id', 'order' => 'ASC'],
            ],
            'includes' => ['blog', 'category'],
        ];

        // Compress and encode the criteria data
        $jsonData = json_encode($criteriaData, \JSON_THROW_ON_ERROR);
        $encodedCriteria = self::gzipAndBase64UrlEncode($jsonData);

        $request = new Request(['_criteria' => $encodedCriteria]);
        $request->setMethod(Request::METHOD_GET);

        $criteria = $this->requestCriteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->staticDefinitionRegistry->get(BlogDefinition::class),
            Context::createDefaultContext()
        );

        static::assertSame(25, $criteria->getLimit());
        static::assertSame(25, $criteria->getOffset()); // page 2 with limit 25 = offset 25
        static::assertCount(1, $criteria->getFilters());
        static::assertCount(1, $criteria->getSorting());
        static::assertSame(['blog', 'category'], $criteria->getIncludes());
    }

    public function testCompressedCriteriaParameterTakesPrecedenceOverIndividualParameters(): void
    {
        $criteriaData = [
            'limit' => 50,
            'page' => 1,
            'filter' => [
                ['type' => 'equals', 'field' => 'active', 'value' => true],
            ],
        ];

        // Compress and encode the criteria data
        $jsonData = json_encode($criteriaData, \JSON_THROW_ON_ERROR);
        $encodedCriteria = self::gzipAndBase64UrlEncode($jsonData);

        // Add conflicting individual parameters
        $request = new Request([
            '_criteria' => $encodedCriteria,
            'limit' => 10, // This should be ignored
            'page' => 3,   // This should be ignored
            'filter' => [  // This should be ignored
                ['type' => 'equals', 'field' => 'name', 'value' => 'test'],
            ],
        ]);
        $request->setMethod(Request::METHOD_GET);

        $criteria = $this->requestCriteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->staticDefinitionRegistry->get(BlogDefinition::class),
            Context::createDefaultContext()
        );

        // Should use values from _criteria parameter, not individual parameters
        static::assertSame(50, $criteria->getLimit());
        static::assertSame(0, $criteria->getOffset()); // page 1 with limit 50 = offset 0
        static::assertCount(1, $criteria->getFilters());

        // Verify the filter is from _criteria, not individual parameter
        $filter = $criteria->getFilters()[0];
        static::assertSame(['blog.active'], $filter->getFields());
    }

    #[WithoutErrorHandler]
    public function testInvalidCompressedCriteriaParameterThrowsException(): void
    {
        // Test integration with invalid base64 - detailed unit tests are in CompressedCriteriaDecoderTest
        $invalidBase64 = 'invalid-base64-format-with-special-chars!@#$%';

        $request = new Request(['_criteria' => $invalidBase64]);
        $request->setMethod(Request::METHOD_GET);

        $this->expectException(DataAbstractionLayerException::class);

        $this->requestCriteriaBuilder->handleRequest(
            $request,
            new Criteria(),
            $this->staticDefinitionRegistry->get(BlogDefinition::class),
            Context::createDefaultContext()
        );
    }

    private static function assertSingleInnerException(
        SearchRequestException $exception,
        InvalidFilterQueryException|InvalidSortQueryException|InvalidLimitQueryException|InvalidPageQueryException $expected,
        string $pointer
    ): void {
        $errors = iterator_to_array($exception->getErrors(), false);
        static::assertCount(1, $errors);

        $error = $errors[0];

        static::assertSame($expected->getErrorCode(), $error['code']);
        static::assertSame($expected->getMessage(), $error['detail']);
        static::assertSame((string) $expected->getStatusCode(), $error['status']);
        static::assertSame($pointer, $error['source']['pointer']);
    }

    private static function gzipAndBase64UrlEncode(string $data): string
    {
        $gzippedData = gzencode($data);
        static::assertNotFalse($gzippedData, 'Gzip compressing failed');

        return Base64::urlEncode($gzippedData);
    }
}
