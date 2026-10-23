<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Framework\DataAbstractionLayer\Search;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Tax\TaxDefinition;
use Contena\Core\Test\Stub\Framework\IdsCollection;

/**
 * @internal
 */
class EntitySearcherTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<PropertyGroupCollection>
     */
    private EntityRepository $groupRepository;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->groupRepository = static::getContainer()->get('property_group.repository');
        $this->blogRepository = static::getContainer()->get('blog.repository');
    }

    public function testScoringWithToManyAssociation(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM blog');

        $blogs = [
            (new BlogBuilder($ids, 'john'))
                ->tag('tag1')
                ->tag('tag2')
                ->tag('tag3')
                ->name('John')
                ->price(100)
                ->build(),
            (new BlogBuilder($ids, 'john.doe'))->name('John Doe')->price(100)->build(),
            (new BlogBuilder($ids, 'doe'))->name('Doe')
                ->category('cat1')
                ->category('cat2')
                ->category('cat3')
                ->tag('tag1')
                ->tag('tag2')
                ->tag('tag3')
                ->price(100)->build(),
        ];

        static::getContainer()->get('blog.repository')
            ->create($blogs, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'John'), 100));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('name', 'Doe'), 100));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('tags.name', 'Doe'), 100));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('categories.name', 'Doe'), 100));

        $result = static::getContainer()->get('blog.repository')->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(100.0, $result->getScore($ids->get('john')));
        static::assertSame(200.0, $result->getScore($ids->get('john.doe')));
        static::assertSame(100.0, $result->getScore($ids->get('doe')));
    }

    public function testIdSearchResultHelpers(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM blog');

        $blogs = [
            (new BlogBuilder($ids, 'john'))->price(100)->build(),
            (new BlogBuilder($ids, 'john.doe'))->name('John Doe')->price(100)->build(),
            (new BlogBuilder($ids, 'doe'))->name('Doe')->price(100)->build(),
        ];

        $context = Context::createDefaultContext();
        static::getContainer()->get('blog.repository')
            ->create($blogs, $context);

        $criteria = new Criteria($ids->getList(['john', 'john.doe', 'doe']));

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, $context);

        $exception = null;

        try {
            $result->getScore($ids->get('john'));
        } catch (\Exception $exception) {
        }
        static::assertInstanceOf(\RuntimeException::class, $exception);

        static::assertSame([], $result->getDataOfId('not-exists'));
        static::assertSame($context, $result->getContext());
        static::assertEquals($criteria, $result->getCriteria());
    }

    public function testDataProperty(): void
    {
        $ids = new IdsCollection();

        $blogs = [
            (new BlogBuilder($ids, 'p1'))
                ->price(100)
                ->build(),
            (new BlogBuilder($ids, 'p2'))
                ->price(100)
                ->build(),
        ];

        static::getContainer()->get('blog.repository')->create($blogs, Context::createDefaultContext());

        $criteria = new Criteria($ids->getList(['p1', 'p2']));
        $result = static::getContainer()->get('blog.repository')->searchIds($criteria, Context::createDefaultContext());

        $increments = static::getContainer()->get(Connection::class)->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)) as id, auto_increment FROM blog WHERE id IN (:ids)',
            ['ids' => $ids->getByteList(['p1', 'p2'])],
            ['ids' => ArrayParameterType::BINARY]
        );

        $data = $result->getData();
        static::assertArrayHasKey($ids->get('p1'), $data);
        static::assertArrayHasKey('blogNumber', $data[$ids->get('p1')]);
        static::assertArrayHasKey('autoIncrement', $data[$ids->get('p1')]);

        static::assertArrayHasKey($ids->get('p2'), $data);
        static::assertArrayHasKey('blogNumber', $data[$ids->get('p2')]);
        static::assertArrayHasKey('autoIncrement', $data[$ids->get('p2')]);
        static::assertSame((int) $increments[$ids->get('p1')], $data[$ids->get('p1')]['autoIncrement']);
        static::assertSame((int) $increments[$ids->get('p2')], $data[$ids->get('p2')]['autoIncrement']);
    }

    public function testTotalCountWithSearchTerm(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $blogs = [
            [
                'id' => $id1,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test matching blog',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
            [
                'id' => $id2,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test matching',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->blogRepository->create($blogs, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('blog.id', [$id1, $id2]));
        $criteria->addQuery(
            new ScoreQuery(new ContainsFilter('blog.name', 'matching'), 1000)
        );
        $criteria->addQuery(
            new ScoreQuery(new ContainsFilter('blog.name', 'test matching'), 1000)
        );

        $criteria->addQuery(
            new ScoreQuery(new ContainsFilter('blog.name', 'matching blog'), 1000)
        );

        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $result = $this->blogRepository->search($criteria, $context);

        static::assertSame(2, $result->getTotal());
        static::assertCount(2, $result->getEntities());
        static::assertSame(1, $result->getPage());
    }

    public function testNextPagesCountIsBoundedByTheLookaheadWindow(): void
    {
        $ids = new IdsCollection();
        $blogs = [];

        foreach (range(1, 8) as $number) {
            $blogNumber = 'next-pages-' . $number;
            $blogs[] = (new BlogBuilder($ids, $blogNumber))->price(100)->build();
        }

        $context = Context::createDefaultContext();
        $this->blogRepository->create($blogs, $context);

        $criteria = new Criteria(array_values($ids->getList(array_map(
            static fn (int $number): string => 'next-pages-' . $number,
            range(1, 8)
        ))));
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NEXT_PAGES);

        $result = $this->blogRepository->search($criteria, $context);

        static::assertCount(1, $result->getEntities());
        static::assertSame(7, $result->getTotal());
    }

    public function testSortingAndTotalCountWithManyAssociation(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $yellowId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $sizeId = Uuid::randomHex();
        $bigId = Uuid::randomHex();
        $smallId = Uuid::randomHex();

        $id = Uuid::randomHex();
        $variant1 = Uuid::randomHex();
        $variant2 = Uuid::randomHex();
        $variant3 = Uuid::randomHex();
        $variant4 = Uuid::randomHex();
        $variant5 = Uuid::randomHex();
        $variant6 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $groups = [
            [
                'id' => $colorId,
                'name' => 'color',
                'options' => [
                    ['id' => $redId, 'name' => 'red'],
                    ['id' => $yellowId, 'name' => 'red'],
                    ['id' => $greenId, 'name' => 'red'],
                ],
            ],
            [
                'id' => $sizeId,
                'name' => 'size',
                'options' => [
                    ['id' => $bigId, 'name' => 'big'],
                    ['id' => $smallId, 'name' => 'small'],
                ],
            ],
        ];

        $this->groupRepository->create($groups, $context);

        $blogs = [
            [
                'id' => $id,
                'blogNumber' => Uuid::randomHex(),
                'name' => 'test',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
            [
                'id' => $variant1,
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $redId], ['id' => $bigId]],
            ],
            [
                'id' => $variant2,
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $redId], ['id' => $smallId]],
            ],
            [
                'id' => $variant3,
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $greenId], ['id' => $bigId]],
            ],
            [
                'id' => $variant4,
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $greenId], ['id' => $smallId]],
            ],
            [
                'id' => $variant5,
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $yellowId], ['id' => $bigId]],
            ],
            [
                'id' => $variant6,
                'blogNumber' => Uuid::randomHex(),
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $yellowId], ['id' => $smallId]],
            ],
        ];

        $this->blogRepository->create($blogs, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.parentId', $id));
        $criteria->addSorting(new FieldSorting('blog.options.groupId'));
        $criteria->addSorting(new FieldSorting('blog.options.id'));

        $criteria->setLimit(25);
        $criteria->setOffset(0);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = $this->blogRepository->search($criteria, $context);

        static::assertSame(1, $result->getPage());
        static::assertSame(6, $result->getTotal());
        static::assertCount(6, $result->getEntities());
    }

    /**
     * @param array{offset: int, limit: int|null, expectedEntities: int} $pagination
     */
    #[DataProvider('lastPagePaginationProvider')]
    public function testExactTotalCountShortCircuitsOnTheLastPage(array $pagination): void
    {
        $context = Context::createDefaultContext();

        $totalMatching = 3;
        $blogNumbers = [];
        $blogs = [];
        for ($i = 0; $i < $totalMatching; ++$i) {
            $blogNumbers[] = 'short-circuit-' . $i;
            $blogs[] = [
                'id' => Uuid::randomHex(),
                'blogNumber' => 'short-circuit-' . $i,
                'name' => 'short circuit blog ' . $i,
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ];
        }
        $this->blogRepository->create($blogs, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('blog.blogNumber', $blogNumbers));
        $criteria->addSorting(new FieldSorting('blog.blogNumber'));
        $criteria->setOffset($pagination['offset']);
        $criteria->setLimit($pagination['limit']);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = $this->blogRepository->search($criteria, $context);

        static::assertSame($totalMatching, $result->getTotal());
        static::assertCount($pagination['expectedEntities'], $result->getEntities());
    }

    /**
     * @return iterable<string, array{array{offset: int, limit: int|null, expectedEntities: int}}>
     */
    public static function lastPagePaginationProvider(): iterable
    {
        // Partial last page (offset 2 + 1 remaining item = 3 total).
        yield 'partial last page' => [['offset' => 2, 'limit' => 2, 'expectedEntities' => 1]];
        // First and only (partial) page.
        yield 'single partial page' => [['offset' => 0, 'limit' => 25, 'expectedEntities' => 3]];
        // No limit at all.
        yield 'no limit' => [['offset' => 0, 'limit' => null, 'expectedEntities' => 3]];
        // Full page with more pages remaining: total still requires the wrapped COUNT(*).
        yield 'first of several full pages' => [['offset' => 0, 'limit' => 1, 'expectedEntities' => 1]];
        // Full page that is exactly the last page.
        yield 'exactly full last page' => [['offset' => 2, 'limit' => 1, 'expectedEntities' => 1]];
        // Empty page past the end: total cannot be derived from the page and falls back to the wrapped COUNT(*).
        yield 'empty page past the end' => [['offset' => 5, 'limit' => 1, 'expectedEntities' => 0]];
    }

    /**
     * @param array<int, int> $expectedTotals
     */
    #[DataProvider('totalCountProvider')]
    public function testTotalCountsEntitiesWithAToManyFilter(?int $limit, int $matchingBlogs, int $expectedEntities, array $expectedTotals, int $offset = 0): void
    {
        $ids = $this->createBlogsWithTwoMatchingTagsEach($matchingBlogs);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('blog.id', array_values($ids->prefixed('blog-'))));
        $criteria->addFilter(new ContainsFilter('blog.tags.name', 'limit-one'));
        $criteria->addSorting(new FieldSorting('blog.id'));
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);

        $expectedData = null;
        foreach ($expectedTotals as $totalCountMode => $expectedTotal) {
            $searchCriteria = clone $criteria;
            $searchCriteria->setTotalCountMode($totalCountMode);
            $result = $this->blogRepository->searchIds($searchCriteria, Context::createDefaultContext());

            static::assertSame($expectedTotal, $result->getTotal(), 'Count mode ' . $totalCountMode);
            static::assertCount($expectedEntities, $result->getIds());
            static::assertNotContains($ids->get('blog-excluded'), $result->getIds());
            $expectedData ??= $result->getData();
            static::assertSame($expectedData, $result->getData(), 'Count mode ' . $totalCountMode);
        }
    }

    /**
     * @param array<int, int> $expectedTotals
     */
    #[DataProvider('totalCountProvider')]
    public function testTotalCountsEntitiesWithASearchTerm(?int $limit, int $matchingBlogs, int $expectedEntities, array $expectedTotals, int $offset = 0): void
    {
        $ids = $this->createBlogsWithTwoMatchingTagsEach($matchingBlogs);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('blog.id', array_values($ids->prefixed('blog-'))));
        $criteria->setTerm('limit one total');
        $criteria->addSorting(new FieldSorting('blog.id'));
        $criteria->setLimit($limit);
        $criteria->setOffset($offset);

        $expectedData = null;
        foreach ($expectedTotals as $totalCountMode => $expectedTotal) {
            $searchCriteria = clone $criteria;
            $searchCriteria->setTotalCountMode($totalCountMode);
            $result = $this->blogRepository->searchIds($searchCriteria, Context::createDefaultContext());

            static::assertSame($expectedTotal, $result->getTotal(), 'Count mode ' . $totalCountMode);
            static::assertCount($expectedEntities, $result->getIds());
            static::assertNotContains($ids->get('blog-excluded'), $result->getIds());
            $expectedData ??= $result->getData();
            static::assertSame($expectedData, $result->getData(), 'Count mode ' . $totalCountMode);
        }
    }

    /**
     * @return iterable<string, array{limit: int|null, matchingBlogs: int, expectedEntities: int, expectedTotals: array<int, int>, offset?: int}>
     */
    public static function totalCountProvider(): iterable
    {
        yield 'a single result per page' => [
            'limit' => 1,
            'matchingBlogs' => 2,
            'expectedEntities' => 1,
            'expectedTotals' => [
                Criteria::TOTAL_COUNT_MODE_NONE => 1,
                Criteria::TOTAL_COUNT_MODE_EXACT => 2,
                Criteria::TOTAL_COUNT_MODE_NEXT_PAGES => 2,
            ],
        ];
        yield 'a full page' => [
            'limit' => 2,
            'matchingBlogs' => 2,
            'expectedEntities' => 2,
            'expectedTotals' => [
                Criteria::TOTAL_COUNT_MODE_NONE => 2,
                Criteria::TOTAL_COUNT_MODE_EXACT => 2,
                Criteria::TOTAL_COUNT_MODE_NEXT_PAGES => 2,
            ],
        ];
        yield 'without a limit' => [
            'limit' => null,
            'matchingBlogs' => 2,
            'expectedEntities' => 2,
            'expectedTotals' => [
                Criteria::TOTAL_COUNT_MODE_NONE => 2,
                Criteria::TOTAL_COUNT_MODE_EXACT => 2,
                Criteria::TOTAL_COUNT_MODE_NEXT_PAGES => 2,
            ],
        ];
        yield 'without a limit after an offset' => [
            'limit' => null,
            'matchingBlogs' => 2,
            'expectedEntities' => 1,
            'expectedTotals' => [
                Criteria::TOTAL_COUNT_MODE_NONE => 1,
                Criteria::TOTAL_COUNT_MODE_EXACT => 2,
                Criteria::TOTAL_COUNT_MODE_NEXT_PAGES => 2,
            ],
            'offset' => 1,
        ];
        yield 'without a limit past the end' => [
            'limit' => null,
            'matchingBlogs' => 2,
            'expectedEntities' => 0,
            'expectedTotals' => [
                Criteria::TOTAL_COUNT_MODE_NONE => 0,
                Criteria::TOTAL_COUNT_MODE_EXACT => 2,
                Criteria::TOTAL_COUNT_MODE_NEXT_PAGES => 2,
            ],
            'offset' => 3,
        ];
        yield 'a partial page' => [
            'limit' => 3,
            'matchingBlogs' => 2,
            'expectedEntities' => 2,
            'expectedTotals' => [
                Criteria::TOTAL_COUNT_MODE_NONE => 2,
                Criteria::TOTAL_COUNT_MODE_EXACT => 2,
                Criteria::TOTAL_COUNT_MODE_NEXT_PAGES => 2,
            ],
        ];
        yield 'lookahead capped below eight matches' => [
            'limit' => 1,
            'matchingBlogs' => 8,
            'expectedEntities' => 1,
            'expectedTotals' => [
                Criteria::TOTAL_COUNT_MODE_NONE => 1,
                Criteria::TOTAL_COUNT_MODE_EXACT => 8,
                Criteria::TOTAL_COUNT_MODE_NEXT_PAGES => 7,
            ],
        ];
        yield 'lookahead capped below fourteen matches' => [
            'limit' => 2,
            'matchingBlogs' => 14,
            'expectedEntities' => 2,
            'expectedTotals' => [
                Criteria::TOTAL_COUNT_MODE_NONE => 2,
                Criteria::TOTAL_COUNT_MODE_EXACT => 14,
                Criteria::TOTAL_COUNT_MODE_NEXT_PAGES => 13,
            ],
        ];
    }

    public function testJsonListEqualsAnyFilter(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $yellowId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $sizeId = Uuid::randomHex();
        $bigId = Uuid::randomHex();
        $smallId = Uuid::randomHex();

        $id = Uuid::randomHex();
        $variant1 = Uuid::randomHex();
        $variant2 = Uuid::randomHex();
        $variant3 = Uuid::randomHex();
        $variant4 = Uuid::randomHex();
        $variant5 = Uuid::randomHex();
        $variant6 = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $groups = [
            [
                'id' => $colorId,
                'name' => 'color',
                'options' => [
                    ['id' => $redId, 'name' => 'red'],
                    ['id' => $yellowId, 'name' => 'red'],
                    ['id' => $greenId, 'name' => 'red'],
                ],
            ],
            [
                'id' => $sizeId,
                'name' => 'size',
                'options' => [
                    ['id' => $bigId, 'name' => 'big'],
                    ['id' => $smallId, 'name' => 'small'],
                ],
            ],
        ];

        $this->groupRepository->create($groups, $context);

        $blogs = [
            [
                'id' => $id,
                'name' => 'test',
                'blogNumber' => 'test1',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
            [
                'id' => $variant1,
                'blogNumber' => 'test2',
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $redId], ['id' => $bigId]],
            ],
            [
                'id' => $variant2,
                'blogNumber' => 'test3',
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $redId], ['id' => $smallId]],
            ],
            [
                'id' => $variant3,
                'blogNumber' => 'test4',
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $greenId], ['id' => $bigId]],
            ],
            [
                'id' => $variant4,
                'blogNumber' => 'test5',
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $greenId], ['id' => $smallId]],
            ],
            [
                'id' => $variant5,
                'blogNumber' => 'test6',
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $yellowId], ['id' => $bigId]],
            ],
            [
                'id' => $variant6,
                'blogNumber' => 'test7',
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $yellowId], ['id' => $smallId]],
            ],
        ];

        $this->blogRepository->create($blogs, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('blog.optionIds', [$yellowId, $redId]));

        $result = $this->blogRepository->search($criteria, $context);

        static::assertSame(4, $result->getTotal());
        static::assertTrue($result->getEntities()->has($variant1));
        static::assertTrue($result->getEntities()->has($variant2));
        static::assertTrue($result->getEntities()->has($variant5));
        static::assertTrue($result->getEntities()->has($variant6));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('blog.optionIds', [$yellowId]));

        $result = $this->blogRepository->search($criteria, $context);
        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->getEntities()->has($variant5));
        static::assertTrue($result->getEntities()->has($variant6));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('blog.optionIds', [$yellowId, $smallId]));

        $result = $this->blogRepository->search($criteria, $context);
        static::assertSame(4, $result->getTotal());
        static::assertTrue($result->getEntities()->has($variant5));
        static::assertTrue($result->getEntities()->has($variant6));
        static::assertTrue($result->getEntities()->has($variant4));
        static::assertTrue($result->getEntities()->has($variant2));
    }

    public function testSortingByProvidedIds(): void
    {
        $ids = new IdsCollection();

        $data = [
            ['id' => $ids->create('t1'), 'name' => 'tax 1', 'taxRate' => 10],
            ['id' => $ids->create('t2'), 'name' => 'tax 2', 'taxRate' => 10],
            ['id' => $ids->create('t3'), 'name' => 'tax 3', 'taxRate' => 10],
            ['id' => $ids->create('t4'), 'name' => 'tax 4', 'taxRate' => 10],
        ];

        static::getContainer()->get('tax.repository')
            ->create($data, Context::createDefaultContext());

        $searcher = new EntitySearcher(
            static::getContainer()->get(Connection::class),
            static::getContainer()->get(EntityDefinitionQueryHelper::class),
            static::getContainer()->get(CriteriaQueryBuilder::class)
        );

        $expected = [
            $ids->get('t4'),
            $ids->get('t2'),
            $ids->get('t1'),
            $ids->get('t3'),
        ];

        $criteria = new Criteria($expected);
        $criteria->addFilter(new EqualsFilter('taxRate', 10));

        $result = $searcher->search(static::getContainer()->get(TaxDefinition::class), $criteria, Context::createDefaultContext());

        static::assertSame($expected, $result->getIds());
    }

    public function testSortingWithToMany(): void
    {
        $defaults = [
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $ids = new IdsCollection();
        $data = [
            array_merge($defaults, [
                'id' => $ids->create('blog-1'),
                'blogNumber' => Uuid::randomHex(),
                'categories' => [
                    ['name' => 'F'],
                    ['name' => 'B'],
                ],
            ]),
            array_merge($defaults, [
                'id' => $ids->create('blog-2'),
                'blogNumber' => Uuid::randomHex(),
                'categories' => [
                    ['name' => 'X'],
                    ['name' => 'A'],
                ],
            ]),
        ];

        static::getContainer()->get('blog.repository')
            ->create($data, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->setIds($ids->getList(['blog-1', 'blog-2']));
        $criteria->addSorting(new FieldSorting('categories.name', FieldSorting::ASCENDING));

        $result = static::getContainer()->get('blog.repository')
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertSame(
            [$ids->get('blog-2'), $ids->get('blog-1')],
            $result->getIds()
        );
    }

    public function testIdsSearchResultReturnFieldPropertyName(): void
    {
        $defaults = [
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
        ];

        $ids = new IdsCollection();
        $data = [
            array_merge($defaults, [
                'id' => $ids->create('blog-1'),
                'blogNumber' => Uuid::randomHex(),
                'categories' => [
                    ['name' => 'F'],
                    ['name' => 'B'],
                ],
            ]),
            array_merge($defaults, [
                'id' => $ids->create('blog-2'),
                'blogNumber' => Uuid::randomHex(),
                'categories' => [
                    ['name' => 'X'],
                    ['name' => 'A'],
                ],
            ]),
        ];

        static::getContainer()->get('blog.repository')
            ->create($data, Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('blogId', array_values($ids->getList(['blog-1', 'blog-2']))));

        /** @var EntityRepository<EntityCollection<Entity>> $blogCategoryRepository */
        $blogCategoryRepository = static::getContainer()->get('blog_category.repository');
        $result = $blogCategoryRepository
            ->searchIds($criteria, Context::createDefaultContext());

        static::assertIsArray($result->getIds());
        static::assertNotCount(0, $result->getIds());

        foreach ($result->getIds() as $resultIds) {
            static::assertIsArray($resultIds);
            static::assertArrayHasKey('blogId', $resultIds);
            static::assertArrayHasKey('categoryId', $resultIds);
        }
    }

    public function testWithCriteriaLimitOfZero(): void
    {
        $ids = new IdsCollection();
        $blog = (new BlogBuilder($ids, 'p1'))
            ->price(100);

        $repository = static::getContainer()->get('blog.repository');

        $repository->create([$blog->build()], Context::createDefaultContext());

        $criteria = new Criteria();
        $criteria->setLimit(0);

        $connection = $this->createMock(Connection::class);
        // connection should not be used if limit is 0
        $connection->expects($this->never())
            ->method('executeQuery');
        $connection->expects($this->never())
            ->method('getDatabasePlatform');

        $searcher = new EntitySearcher(
            $connection,
            static::getContainer()->get(EntityDefinitionQueryHelper::class),
            static::getContainer()->get(CriteriaQueryBuilder::class),
        );

        $result = $searcher->search(
            static::getContainer()->get(BlogDefinition::class),
            $criteria,
            Context::createDefaultContext()
        );

        static::assertSame(0, $result->getTotal());
    }

    private function createBlogsWithTwoMatchingTagsEach(int $matchingBlogs): IdsCollection
    {
        $ids = new IdsCollection();
        $blogs = [];

        foreach (range(1, $matchingBlogs) as $number) {
            $blogs[] = (new BlogBuilder($ids, 'blog-' . $number))->name('limit one total')->price(100)
                ->tag('limit-one-' . $number . '-a')->tag('limit-one-' . $number . '-b')->build();
        }

        $blogs[] = (new BlogBuilder($ids, 'blog-excluded'))->name('unrelated item')->price(100)
            ->tag('unrelated-a')->tag('unrelated-b')->build();

        $this->blogRepository->create($blogs, Context::createDefaultContext());

        return $ids;
    }
}
