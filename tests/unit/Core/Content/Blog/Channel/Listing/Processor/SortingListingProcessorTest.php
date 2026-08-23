<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\Processor\SortingListingProcessor;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingEntity;
use Contena\Core\Content\Blog\Exception\BlogSortingNotFoundException;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SortingListingProcessor::class)]
class SortingListingProcessorTest extends TestCase
{
    private string $barId;

    private string $fooId;

    private string $testId;

    /**
     * @param FieldSorting[] $expected
     */
    #[DataProvider('prepareProvider')]
    public function testPrepare(string $sorting, bool $testWithAvailableSortings, array $expected): void
    {
        $sortingRepository = StaticEntityRepository::of(BlogSortingCollection::class, [$this->buildSortings()]);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            $sortingRepository
        );

        $processor->prepare(
            new Request(['order' => $sorting, 'availableSortings' => $testWithAvailableSortings ? $this->buildAvailableSortings() : []]),
            $criteria = new Criteria(),
            static::createStub(ChannelContext::class)
        );

        static::assertEquals($expected, $criteria->getSorting());
    }

    #[DataProvider('prepareDefaultSearchResultSortingProvider')]
    public function testPrepareDefaultSearchResultSorting(Request $requested): void
    {
        $blogSorting = new BlogSortingEntity();
        $blogSorting->setId(Uuid::randomHex());
        $blogSorting->assign([
            'key' => 'score',
            'fields' => [
                ['field' => '_score', 'priority' => 1, 'order' => 'DESC'],
            ],
        ]);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new BlogSortingCollection([$blogSorting]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([
                'core.listing.defaultSearchResultSorting' => Uuid::randomHex(),
            ]),
            $repository
        );

        $processor->prepare(
            $requested,
            $criteria = new Criteria(),
            static::createStub(ChannelContext::class)
        );

        static::assertEquals([
            new FieldSorting('_score', FieldSorting::DESCENDING),
            new FieldSorting('id', FieldSorting::ASCENDING),
        ], $criteria->getSorting());
    }

    #[DataProvider('prepareDefaultSearchResultSortingProvider')]
    public function testPrepareWithFallbackSorting(Request $requested): void
    {
        $blogSorting = new BlogSortingEntity();
        $blogSorting->setId(Uuid::randomHex());
        $blogSorting->assign([
            'key' => 'name-asc',
            'fields' => [
                ['field' => 'name', 'priority' => 1, 'order' => 'ASC'],
            ],
        ]);

        $repository = static::createStub(EntityRepository::class);
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                1,
                new BlogSortingCollection([$blogSorting]),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([
                'core.listing.defaultSearchResultSorting' => Uuid::randomHex(),
            ]),
            $repository
        );

        $criteria = new Criteria();
        $criteria->setTerm('test');
        $processor->prepare(
            $requested,
            $criteria,
            static::createStub(ChannelContext::class)
        );

        static::assertEquals([
            new FieldSorting('name', FieldSorting::ASCENDING),
            new FieldSorting('_score', FieldSorting::DESCENDING),
            new FieldSorting('id', FieldSorting::ASCENDING),
        ], $criteria->getSorting());
    }

    #[DataProvider('processProvider')]
    public function testProcess(string $requested, ?string $expected): void
    {
        $sortings = $this->buildSortings();

        $sortingRepository = StaticEntityRepository::of(BlogSortingCollection::class, [$sortings]);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            $sortingRepository
        );

        $result = BlogListingResult::fromSearchResult(
            new EntitySearchResult(1, new BlogCollection(), null, new Criteria(), Context::createDefaultContext())
        );
        $result->getCriteria()->addExtension('sortings', $sortings);

        $processor->process(
            new Request(['order' => $requested]),
            $result,
            static::createStub(ChannelContext::class)
        );

        static::assertSame($expected, $result->getSorting());
    }

    public function testResolveUsesRuntimeSortingChanges(): void
    {
        $sortings = $this->buildSortings();
        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            StaticEntityRepository::of(BlogSortingCollection::class, [$sortings])
        );
        $request = new Request(['order' => 'foo']);
        $criteria = new Criteria();
        $context = static::createStub(ChannelContext::class);

        $processor->prepare($request, $criteria, $context);
        $sortings->getByKey('foo')?->setFields([
            ['field' => 'runtime', 'priority' => 1, 'order' => 'ASC', 'naturalSorting' => null],
        ]);

        $processor->resolve($request, $criteria, $context);

        static::assertEquals([
            new FieldSorting('runtime', FieldSorting::ASCENDING),
            new FieldSorting('id', FieldSorting::ASCENDING),
        ], $criteria->getSorting());
    }

    #[DataProvider('wrongSortingTypeProvider')]
    public function testWrongSortingTypeThrowsException(mixed $requested): void
    {
        $this->expectException(BlogSortingNotFoundException::class);

        $sortingRepository = StaticEntityRepository::of(BlogSortingCollection::class, [
            $this->buildSortings(),
        ]);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            $sortingRepository
        );

        $processor->prepare(
            new Request(['order' => $requested]),
            new Criteria(),
            static::createStub(ChannelContext::class)
        );
    }

    public static function prepareDefaultSearchResultSortingProvider(): \Generator
    {
        yield 'Search term in post request' => [
            'requested' => new Request([], ['search' => 'test']),
        ];

        yield 'Search term in query' => [
            'requested' => new Request(['search' => 'test']),
        ];
    }

    public static function prepareProvider(): \Generator
    {
        yield 'Requested foo sorting will be accepted' => [
            'sorting' => 'foo',
            'testWithAvailableSortings' => false,
            'expected' => [
                new FieldSorting('id', FieldSorting::ASCENDING),
                new FieldSorting('foo', FieldSorting::DESCENDING),
            ],
        ];

        yield 'Requested foo sorting with available sortings will be accepted' => [
            'sorting' => 'foo',
            'testWithAvailableSortings' => true,
            'expected' => [
                new FieldSorting('id', FieldSorting::ASCENDING),
                new FieldSorting('foo', FieldSorting::DESCENDING),
            ],
        ];

        yield 'Requested bar sorting will be accepted' => [
            'sorting' => 'bar',
            'testWithAvailableSortings' => false,
            'expected' => [
                new FieldSorting('id', FieldSorting::ASCENDING),
                new FieldSorting('bar', FieldSorting::DESCENDING),
            ],
        ];

        yield 'Requested bar sorting with available sortings will be accepted' => [
            'sorting' => 'bar',
            'testWithAvailableSortings' => true,
            'expected' => [],
        ];

        yield 'Requested unknown sorting will be accepted' => [
            'sorting' => 'test',
            'testWithAvailableSortings' => false,
            'expected' => [],
        ];

        yield 'Requested unknown with available sortings sorting will be accepted' => [
            'sorting' => 'test',
            'testWithAvailableSortings' => true,
            'expected' => [],
        ];
    }

    public static function processProvider(): \Generator
    {
        yield 'Requested foo sorting will be accepted' => [
            'requested' => 'foo',
            'expected' => 'foo',
        ];

        yield 'Requested bar sorting will be accepted' => [
            'requested' => 'bar',
            'expected' => 'bar',
        ];

        yield 'Requested unknown test sorting will be accepted' => [
            'requested' => 'test',
            'expected' => null,
        ];
    }

    public static function wrongSortingTypeProvider(): \Generator
    {
        yield 'Request of type null will throw exception' => ['requested' => null];
        yield 'Request of type array will throw exception' => ['requested' => []];
        yield 'Request of type int will throw exception' => ['requested' => 1];
    }

    private function buildSortings(): BlogSortingCollection
    {
        $this->fooId = Uuid::randomHex();
        $this->barId = Uuid::randomHex();
        $this->testId = Uuid::randomHex();

        $sortings = [
            new BlogSortingEntity()->assign([
                'key' => 'foo',
                'fields' => [
                    ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
            new BlogSortingEntity()->assign([
                'key' => 'bar',
                'fields' => [
                    ['field' => 'bar', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
        ];

        $sortings[0]->setId($this->fooId);
        $sortings[1]->setId($this->barId);

        return new BlogSortingCollection($sortings);
    }

    /**
     * @return BlogSortingEntity[]
     */
    private function buildAvailableSortings(): array
    {
        $availableSortings = [
            $this->fooId => new BlogSortingEntity()->assign([
                'key' => 'foo',
                'fields' => [
                    ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
            $this->testId => new BlogSortingEntity()->assign([
                'key' => 'test',
                'fields' => [
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                    ['field' => 'test', 'priority' => 3, 'order' => 'DESC'],
                ],
            ]),
        ];

        $availableSortings[$this->fooId]->setId($this->fooId);

        return $availableSortings;
    }
}
