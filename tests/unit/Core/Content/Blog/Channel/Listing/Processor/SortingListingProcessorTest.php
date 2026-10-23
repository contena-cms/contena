<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing\Processor;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\Processor\SortingListingProcessor;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingCollection;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingDefinition;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingEntity;
use Contena\Core\Content\Blog\Events\BlogListingCollectSortingEvent;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

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
        $sortingRepository = new StaticEntityRepository([$this->buildSortings()]);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            $sortingRepository,
            static::createStub(EventDispatcherInterface::class)
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
                BlogSortingDefinition::ENTITY_NAME,
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
            $repository,
            static::createStub(EventDispatcherInterface::class)
        );

        $processor->prepare(
            $requested,
            $criteria = new Criteria(),
            static::createStub(ChannelContext::class)
        );

        static::assertEquals([
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
                BlogSortingDefinition::ENTITY_NAME,
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
            $repository,
            static::createStub(EventDispatcherInterface::class)
        );

        $criteria = new Criteria();
        $criteria->setTerm('test');
        $processor->prepare(
            $requested,
            $criteria,
            static::createStub(ChannelContext::class)
        );

        static::assertEquals([
        ], $criteria->getSorting());
    }

    #[DataProvider('processProvider')]
    public function testProcess(string $requested, ?string $expected): void
    {
        $sortings = $this->buildSortings();

        $sortingRepository = new StaticEntityRepository([$sortings]);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            $sortingRepository,
            static::createStub(EventDispatcherInterface::class)
        );

        $result = new BlogListingResult($requested, 1, new BlogCollection(), null, new Criteria(), Context::createDefaultContext());
        $result->getCriteria()->addExtension(SortingListingProcessor::SORTINGS_EXTENSION, $sortings);

        $processor->process(
            new Request(['order' => $requested]),
            $result,
            static::createStub(ChannelContext::class)
        );

        static::assertSame($expected, $result->getSorting());
    }

    #[DataProvider('wrongSortingTypeProvider')]
    public function testWrongSortingTypeThrowsException(mixed $requested): void
    {
        $this->expectException(BlogException::class);

        $sortingRepository = new StaticEntityRepository([
            $this->buildSortings(),
        ]);

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            $sortingRepository,
            static::createStub(EventDispatcherInterface::class)
        );

        $processor->prepare(
            new Request(['order' => $requested]),
            new Criteria(),
            static::createStub(ChannelContext::class)
        );
    }

    #[DataProvider('runtimeSortingOrderProvider')]
    public function testPrepareAppliesSortingRegisteredInCollectSortingEvent(string $order, string $expectedDirection): void
    {
        $runtimeSorting = new BlogSortingEntity();
        $runtimeSorting->setId(Uuid::randomHex());
        $runtimeSorting->assign([
            'key' => 'my-custom-runtime-sort',
            'fields' => [
                ['field' => 'blog.name', 'priority' => 1, 'order' => $order, 'naturalSorting' => 0],
            ],
        ]);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(
            BlogListingCollectSortingEvent::class,
            static function (BlogListingCollectSortingEvent $event) use ($runtimeSorting): void {
                $event->getSortings()->add($runtimeSorting);
            }
        );

        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            new StaticEntityRepository([$this->buildSortings()]),
            $dispatcher
        );

        $processor->prepare(
            new Request(['order' => 'my-custom-runtime-sort']),
            $criteria = new Criteria(),
            static::createStub(ChannelContext::class)
        );

        static::assertEquals([
            new FieldSorting('blog.name', $expectedDirection),
        ], $criteria->getSorting());

        $sortings = $criteria->getExtension(SortingListingProcessor::SORTINGS_EXTENSION);
        static::assertInstanceOf(BlogSortingCollection::class, $sortings);
        static::assertNotNull($sortings->getByKey('my-custom-runtime-sort'));
    }

    public function testPrepareKeepsSortingAlreadyPresentOnTheCriteria(): void
    {
        $processor = new SortingListingProcessor(
            new StaticSystemConfigService([]),
            new StaticEntityRepository([$this->buildSortings()]),
            static::createStub(EventDispatcherInterface::class)
        );

        $criteria = new Criteria();
        $criteria->addSorting(new FieldSorting('blog.stock', FieldSorting::DESCENDING));

        $processor->prepare(
            new Request(['order' => 'foo']),
            $criteria,
            static::createStub(ChannelContext::class)
        );

        static::assertEquals([
        ], $criteria->getSorting());
    }

    public static function runtimeSortingOrderProvider(): \Generator
    {
        yield 'ascending runtime sorting' => ['order' => 'asc', 'expectedDirection' => FieldSorting::ASCENDING];
        yield 'descending runtime sorting' => ['order' => 'desc', 'expectedDirection' => FieldSorting::DESCENDING];
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
            ],
        ];

        yield 'Requested foo sorting with available sortings will be accepted' => [
            'sorting' => 'foo',
            'testWithAvailableSortings' => true,
            'expected' => [
            ],
        ];

        yield 'Requested bar sorting will be accepted' => [
            'sorting' => 'bar',
            'testWithAvailableSortings' => false,
            'expected' => [
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
            (new BlogSortingEntity())->assign([
                'key' => 'foo',
                'fields' => [
                    ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
            (new BlogSortingEntity())->assign([
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
            $this->fooId => (new BlogSortingEntity())->assign([
                'key' => 'foo',
                'fields' => [
                    ['field' => 'foo', 'priority' => 1, 'order' => 'DESC'],
                    ['field' => 'id', 'priority' => 2, 'order' => 'ASC'],
                ],
            ]),
            $this->testId => (new BlogSortingEntity())->assign([
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
