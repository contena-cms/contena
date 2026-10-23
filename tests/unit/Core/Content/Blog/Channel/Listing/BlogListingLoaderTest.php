<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Blog\Channel\Listing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\Blog\Channel\Search\ResolvedCriteriaBlogSearchRoute;
use Contena\Core\Content\Blog\Events\BlogListingResolvePreviewEvent;
use Contena\Core\Content\Blog\Extension\LoadPreviewExtension;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\Filter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(BlogListingLoader::class)]
class BlogListingLoaderTest extends TestCase
{
    /**
     * @var MockObject&ChannelRepository<BlogCollection>
     */
    private MockObject&ChannelRepository $blogRepository;

    private MockObject&SystemConfigService $systemConfigService;

    private Stub&Connection $connection;

    private EventDispatcher $eventDispatcher;

    private Stub&AbstractBlogCloseoutFilterFactory $blogCloseoutFilterFactory;

    private ChannelContext $channelContext;

    protected function setUp(): void
    {
        $this->blogRepository = $this->createMock(ChannelRepository::class);
        $this->systemConfigService = $this->createMock(SystemConfigService::class);
        $this->connection = static::createStub(Connection::class);
        $this->eventDispatcher = new EventDispatcher();
        $this->blogCloseoutFilterFactory = static::createStub(AbstractBlogCloseoutFilterFactory::class);
        $this->channelContext = Generator::generateChannelContext();
    }

    public function testLoadAddsDisplayGroupGroupingByDefault(): void
    {
        $this->systemConfigService
            ->expects($this->exactly(2))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $channelId): bool {
                static::assertSame($this->channelContext->getChannelId(), $channelId);

                return match ($key) {
                    'core.listing.partialDataLoading' => false,
                    'core.listing.hideCloseoutBlogsWhenOutOfStock' => false,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $this->blogRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria): IdSearchResult {
                static::assertCount(1, $criteria->getGroupFields());
                static::assertTrue(array_filter(
                    $criteria->getFilters(),
                    static fn ($filter): bool => $filter instanceof NotEqualsFilter && $filter->getField() === 'displayGroup'
                ) !== []);

                return $this->createIdSearchResult($criteria, [
                    'red-l' => ['score' => 10.0],
                    'blue-m' => ['score' => 5.0],
                ]);
            });

        $this->blogRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function (LoadPreviewExtension $extension): void {
                $extension->result = array_combine($extension->ids, $extension->ids);
                $extension->stopPropagation();
            }
        );

        $this->blogRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertSame(['red-l', 'blue-m'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('options'));

                return $this->createBlogSearchResult($criteria, ['red-l', 'blue-m']);
            });

        $result = $this->createLoader()->load(new Criteria(), $this->channelContext);

        static::assertSame(['red-l', 'blue-m'], array_values($result->getEntities()->getIds()));
        static::assertSame(2, $result->getTotal());
    }

    public function testLoadSkipsGroupingAndPreviewWhenDirectVariantStateIsPresent(): void
    {
        $previewLoaded = false;
        $resolvePreviewEventSeen = false;

        $this->systemConfigService
            ->expects($this->exactly(2))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $channelId): bool {
                static::assertSame($this->channelContext->getChannelId(), $channelId);

                return match ($key) {
                    'core.listing.partialDataLoading' => false,
                    'core.listing.hideCloseoutBlogsWhenOutOfStock' => false,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $this->blogRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria): IdSearchResult {
                static::assertCount(0, $criteria->getGroupFields());
                static::assertFalse(array_filter(
                    $criteria->getFilters(),
                    static fn ($filter): bool => $filter instanceof NotEqualsFilter && $filter->getField() === 'displayGroup'
                ) !== []);

                return $this->createIdSearchResult($criteria, [
                    'variant-a' => ['score' => 10.0],
                    'variant-b' => ['score' => 5.0],
                ]);
            });

        $this->blogRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function () use (&$previewLoaded): void {
                $previewLoaded = true;
            }
        );

        $this->eventDispatcher->addListener(
            BlogListingResolvePreviewEvent::class,
            static function (BlogListingResolvePreviewEvent $event) use (&$resolvePreviewEventSeen): void {
                $resolvePreviewEventSeen = true;
                static::assertFalse($event->hasOptionFilter());
                static::assertSame([
                    'variant-a' => 'variant-a',
                    'variant-b' => 'variant-b',
                ], $event->getMapping());
            }
        );

        $this->blogRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertSame(['variant-a', 'variant-b'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('options'));

                return $this->createBlogSearchResult($criteria, ['variant-a', 'variant-b']);
            });

        $criteria = new Criteria();
        $criteria->addState(BlogListingLoader::STATE_SKIP_ADD_GROUPING);

        $result = $this->createLoader()->load($criteria, $this->channelContext);

        static::assertFalse($previewLoaded);
        static::assertTrue($resolvePreviewEventSeen);
        static::assertSame(['variant-a', 'variant-b'], array_values($result->getEntities()->getIds()));
    }

    public function testLoadResolvesPreviewOnSearchRouteWithOptionPostFilterWhenFindBestVariantIsDisabled(): void
    {
        $previewLoaded = false;
        $resolvePreviewEventSeen = false;
        $configKeys = [];

        $this->systemConfigService
            ->expects($this->exactly(4))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $channelId) use (&$configKeys): bool {
                static::assertSame($this->channelContext->getChannelId(), $channelId);
                $configKeys[] = $key;

                return match ($key) {
                    'core.listing.partialDataLoading' => false,
                    'core.listing.hideCloseoutBlogsWhenOutOfStock' => false,
                    'core.listing.findBestVariant' => false,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $this->blogRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria): IdSearchResult {
                static::assertSame([], $criteria->getIds());

                return $this->createIdSearchResult($criteria, [
                    'variant-id' => ['score' => 10.0],
                ]);
            });

        $this->blogRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function (LoadPreviewExtension $extension) use (&$previewLoaded): void {
                $previewLoaded = true;
                static::assertEquals([new EqualsFilter('blog.options.id', 'option-id')], $extension->postFilters);
                $extension->result = [
                    'variant-id' => 'preview-id',
                ];
                $extension->stopPropagation();
            }
        );

        $this->eventDispatcher->addListener(
            BlogListingResolvePreviewEvent::class,
            static function (BlogListingResolvePreviewEvent $event) use (&$resolvePreviewEventSeen): void {
                $resolvePreviewEventSeen = true;
                static::assertTrue($event->hasOptionFilter());
                static::assertSame(['variant-id' => 'preview-id'], $event->getMapping());
            }
        );

        $this->blogRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertSame(['preview-id'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('options'));

                return $this->createBlogSearchResult($criteria, ['preview-id']);
            });

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaBlogSearchRoute::STATE);
        $criteria->addPostFilter(new EqualsFilter('blog.options.id', 'option-id'));

        $result = $this->createLoader()->load($criteria, $this->channelContext);

        static::assertTrue($previewLoaded);
        static::assertTrue($resolvePreviewEventSeen);
        static::assertSame([
            'core.listing.partialDataLoading',
            'core.listing.findBestVariant',
            'core.listing.hideCloseoutBlogsWhenOutOfStock',
            'core.listing.findBestVariant',
        ], $configKeys);
        static::assertSame(['preview-id'], array_values($result->getEntities()->getIds()));
    }

    public function testLoadSkipsPreviewOnSearchRouteWhenFindBestVariantIsEnabled(): void
    {
        $previewLoaded = false;

        $this->systemConfigService
            ->expects($this->exactly(4))
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $channelId): bool {
                static::assertSame($this->channelContext->getChannelId(), $channelId);

                return match ($key) {
                    'core.listing.partialDataLoading' => false,
                    'core.listing.hideCloseoutBlogsWhenOutOfStock' => false,
                    'core.listing.findBestVariant' => true,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $this->blogRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria): IdSearchResult {
                static::assertSame([], $criteria->getIds());
                static::assertContains(Criteria::STATE_SCORE_RANKED_GROUPING, $criteria->getStates());

                return $this->createIdSearchResult($criteria, [
                    'variant-id' => ['score' => 10.0],
                ]);
            });

        $this->blogRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $this->eventDispatcher->addListener(
            ExtensionDispatcher::pre(LoadPreviewExtension::NAME),
            static function () use (&$previewLoaded): void {
                $previewLoaded = true;
            }
        );

        $this->blogRepository
            ->expects($this->once())
            ->method('search')
            ->willReturnCallback(function (Criteria $criteria): EntitySearchResult {
                static::assertSame(['variant-id'], $criteria->getIds());
                static::assertTrue($criteria->hasAssociation('options'));

                return $this->createBlogSearchResult($criteria, ['variant-id']);
            });

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaBlogSearchRoute::STATE);

        $result = $this->createLoader()->load($criteria, $this->channelContext);

        static::assertFalse($previewLoaded);
        static::assertSame(['variant-id'], array_values($result->getEntities()->getIds()));
    }

    public function testScoreRankedGroupingExcludesBlogsWithVariants(): void
    {
        $actual = $this->resolveSearchIds(findBestVariant: true);

        $expected = new Criteria();
        $expected->addState(ResolvedCriteriaBlogSearchRoute::STATE);
        $expected->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $expected->addGroupField(new FieldGrouping('displayGroup'));
        $expected->addFilter(new NotEqualsFilter('displayGroup', null));
        $expected->addState(Criteria::STATE_SCORE_RANKED_GROUPING);
        $expected->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('childCount', 0),
            new EqualsFilter('childCount', null),
        ]));

        static::assertEquals($expected, $actual);
    }

    public function testNonBestVariantSearchDoesNotExcludeBlogsWithVariants(): void
    {
        $criteria = $this->resolveSearchIds(findBestVariant: false);

        static::assertFalse($criteria->hasState(Criteria::STATE_SCORE_RANKED_GROUPING));
        static::assertNull($this->findChildCountFilter($criteria));
    }

    public function testLoadPreviewsChecksMainVariantAgainstPostFilters(): void
    {
        $ids = new IdsCollection();
        $postFilter = new EqualsFilter('blog.options.id', $ids->get('green'));

        [$availabilityCriteria, $resultIds] = $this->loadWithPreviews(
            [$this->createPreviewConfigRow($ids, 'found-a', 'parent-a', ['mainVariantId' => $ids->get('main-a')])],
            [$postFilter],
            []
        );

        static::assertContainsEquals(new AndFilter([$postFilter]), $availabilityCriteria->getFilters());
        static::assertSame([$ids->get('found-a')], $resultIds);
    }

    public function testLoadPreviewsExemptsParentFromPostFilters(): void
    {
        $ids = new IdsCollection();
        $postFilter = new EqualsFilter('blog.options.id', $ids->get('green'));

        [$availabilityCriteria, $resultIds] = $this->loadWithPreviews(
            [
                $this->createPreviewConfigRow($ids, 'found-a', 'parent-a', ['mainVariantId' => $ids->get('main-a')]),
                $this->createPreviewConfigRow($ids, 'found-b', 'parent-b', ['mainVariantId' => $ids->get('main-b'), 'displayParent' => true]),
            ],
            [$postFilter],
            [$ids->get('parent-b')]
        );

        static::assertContainsEquals(new OrFilter([
            new EqualsAnyFilter('id', [$ids->get('parent-b')]),
            new AndFilter([$postFilter]),
        ]), $availabilityCriteria->getFilters());
        static::assertSame([$ids->get('found-a'), $ids->get('parent-b')], $resultIds);
    }

    public function testLoadPreviewsWithoutPostFiltersUsesMainVariant(): void
    {
        $ids = new IdsCollection();

        [$availabilityCriteria, $resultIds] = $this->loadWithPreviews(
            [$this->createPreviewConfigRow($ids, 'found-a', 'parent-a', ['mainVariantId' => $ids->get('main-a')])],
            [],
            [$ids->get('main-a')]
        );

        static::assertCount(1, $availabilityCriteria->getFilters());
        static::assertSame([$ids->get('main-a')], $resultIds);
    }

    /**
     * @param list<array{variantListingConfig: string, id: string, parentId: string}> $configRows
     * @param list<Filter> $postFilters
     * @param list<string> $availableIds
     *
     * @return array{Criteria, list<string>}
     */
    private function loadWithPreviews(array $configRows, array $postFilters, array $availableIds): array
    {
        $this->systemConfigService
            ->expects($this->atLeastOnce())
            ->method('getBool')
            ->willReturnCallback(static fn (string $key): bool => match ($key) {
                'core.listing.partialDataLoading', 'core.listing.hideCloseoutBlogsWhenOutOfStock' => false,
                default => throw new \RuntimeException('Unexpected config key ' . $key),
            });

        $this->connection->method('fetchAllAssociative')->willReturn($configRows);

        $availabilityCriteria = null;
        $this->blogRepository
            ->expects($this->exactly(2))
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria) use ($configRows, $availableIds, &$availabilityCriteria): IdSearchResult {
                if ($criteria->getIds() === []) {
                    return $this->createIdSearchResult($criteria, array_fill_keys(array_column($configRows, 'id'), []));
                }

                $availabilityCriteria = $criteria;

                return $this->createIdSearchResult($criteria, array_fill_keys($availableIds, []));
            });

        $this->blogRepository->method('aggregate')->willReturn(new AggregationResultCollection());
        $this->blogRepository
            ->method('search')
            ->willReturnCallback(fn (Criteria $criteria): EntitySearchResult => $this->createBlogSearchResult($criteria, array_values(array_filter($criteria->getIds(), 'is_string'))));

        $criteria = new Criteria();
        foreach ($postFilters as $postFilter) {
            $criteria->addPostFilter($postFilter);
        }

        $result = $this->createLoader()->load($criteria, $this->channelContext);

        static::assertInstanceOf(Criteria::class, $availabilityCriteria);

        return [$availabilityCriteria, array_values($result->getEntities()->getIds())];
    }

    /**
     * @param array<string, mixed> $variantListingConfig
     *
     * @return array{variantListingConfig: string, id: string, parentId: string}
     */
    private function createPreviewConfigRow(IdsCollection $ids, string $childKey, string $parentKey, array $variantListingConfig): array
    {
        return [
            'variantListingConfig' => json_encode($variantListingConfig, \JSON_THROW_ON_ERROR),
            'id' => $ids->get($childKey),
            'parentId' => $ids->get($parentKey),
        ];
    }

    private function createLoader(): BlogListingLoader
    {
        return new BlogListingLoader(
            $this->blogRepository,
            $this->systemConfigService,
            $this->connection,
            $this->eventDispatcher,
            $this->blogCloseoutFilterFactory,
            new ExtensionDispatcher($this->eventDispatcher)
        );
    }

    /**
     * @param array<string, array<string, mixed>> $ids
     */
    private function createIdSearchResult(Criteria $criteria, array $ids): IdSearchResult
    {
        $data = [];
        foreach ($ids as $id => $row) {
            $data[$id] = [
                'primaryKey' => $id,
                'data' => $row,
            ];
        }

        return new IdSearchResult(\count($data), $data, $criteria, $this->channelContext->getContext());
    }

    /**
     * @param list<string> $ids
     *
     * @return EntitySearchResult<BlogCollection>
     */
    private function createBlogSearchResult(Criteria $criteria, array $ids): EntitySearchResult
    {
        $blogs = new BlogCollection();
        foreach ($ids as $id) {
            $blogs->add((new BlogEntity())->assign(['id' => $id]));
        }

        return new EntitySearchResult('blog', $blogs->count(), $blogs, new AggregationResultCollection(), $criteria, $this->channelContext->getContext());
    }

    /**
     * Runs a search-route listing through the public loader and returns the criteria the
     * loader resolved the ids with.
     */
    private function resolveSearchIds(bool $findBestVariant): Criteria
    {
        $this->systemConfigService
            ->expects($this->atLeastOnce())
            ->method('getBool')
            ->willReturnCallback(function (string $key, string $channelId) use ($findBestVariant): bool {
                static::assertSame($this->channelContext->getChannelId(), $channelId);

                return match ($key) {
                    'core.listing.findBestVariant' => $findBestVariant,
                    'core.listing.partialDataLoading', 'core.listing.hideCloseoutBlogsWhenOutOfStock' => false,
                    default => throw new \RuntimeException('Unexpected config key ' . $key),
                };
            });

        $resolved = null;
        $this->blogRepository
            ->expects($this->once())
            ->method('searchIds')
            ->willReturnCallback(function (Criteria $criteria) use (&$resolved): IdSearchResult {
                $resolved = $criteria;

                // no ids: the loader stops right after resolving them
                return $this->createIdSearchResult($criteria, []);
            });

        $this->blogRepository
            ->expects($this->once())
            ->method('aggregate')
            ->willReturn(new AggregationResultCollection());

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaBlogSearchRoute::STATE);

        $this->createLoader()->load($criteria, $this->channelContext);

        static::assertInstanceOf(Criteria::class, $resolved);

        return $resolved;
    }

    private function findChildCountFilter(Criteria $criteria): ?MultiFilter
    {
        foreach ($criteria->getFilters() as $filter) {
            if (!$filter instanceof MultiFilter) {
                continue;
            }

            if (\in_array('childCount', $filter->getFields(), true)) {
                return $filter;
            }
        }

        return null;
    }
}
