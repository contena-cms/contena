<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel\Listing;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Events\BlogListingResolvePreviewEvent;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Search\ResolvedCriteriaBlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRoute;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Struct\ArrayEntity;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TaxAddToChannelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseHelper\CallableClass;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class BlogListingLoaderTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;
    use TaxAddToChannelTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private BlogListingLoader $blogListingLoader;

    private ChannelContext $channelContext;

    private SystemConfigService $systemConfigService;

    private ?string $blogId = null;

    private ?string $mainVariantId = null;

    /**
     * @var array<string>
     */
    private array $optionIds = [];

    /**
     * @var array<string>
     */
    private array $variantIds = [];

    /**
     * @var array<string>
     */
    private array $groupIds = [];

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->blogListingLoader = static::getContainer()->get(BlogListingLoader::class);
        $this->channelContext = $this->createChannelContext();
        $this->systemConfigService = static::getContainer()->get(SystemConfigService::class);

        parent::setUp();
    }

    public function testResolvePreviewEvent(): void
    {
        $ids = new IdsCollection();
        $blog = (new BlogBuilder($ids, 'p1'))
            ->price(100)
            ->visibility()
            ->build();
        static::getContainer()->get('blog.repository')->create([$blog], Context::createDefaultContext());

        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->once())->method('__invoke');
        EventHookDispatcher::fromContainer(static::getContainer())->on(BlogListingResolvePreviewEvent::class, $listener);
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $criteria = new Criteria($ids->getList(['p1']));
        $this->blogListingLoader->load($criteria, $context);
    }

    public function testMainVariant(): void
    {
        $this->createBlog([], true);

        $listing = $this->fetchListing();

        static::assertSame(1, $listing->getTotal());

        $mainVariant = $listing->getEntities()->first();
        static::assertNotNull($mainVariant);

        static::assertSame($this->mainVariantId, $mainVariant->getId());
        static::assertContains($this->optionIds['red'], $mainVariant->getOptionIds() ?: []);
        static::assertContains($this->optionIds['l'], $mainVariant->getOptionIds() ?: []);
        static::assertTrue($mainVariant->hasExtension('search'));

        static::assertTrue($listing->getCriteria()->hasState(Criteria::STATE_ELASTICSEARCH_AWARE));
    }

    public function testMainVariantInactive(): void
    {
        $this->createBlog([], true);

        // update main variant to be inactive
        $this->blogRepository->update([[
            'id' => $this->mainVariantId,
            'active' => false,
        ]], $this->channelContext->getContext());

        $listing = $this->fetchListing();

        // another random variant of the blog should be displayed
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertNotSame($this->mainVariantId, $variantId);
        static::assertContains($variantId, $this->variantIds);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public function testMainVariantRemoved(): void
    {
        $this->createBlog([], true);

        $this->blogRepository->delete([['id' => $this->mainVariantId]], $this->channelContext->getContext());

        $listing = $this->fetchListing();

        // another random variant of the blog should be displayed
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertNotSame($this->mainVariantId, $variantId);
        static::assertContains($variantId, $this->variantIds);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public function testMainVariantOutOfStock(): void
    {
        $this->createBlog([], true);

        $this->systemConfigService->set(
            'core.listing.hideCloseoutBlogsWhenOutOfStock',
            true,
            $this->channelContext->getChannelId()
        );

        // update main variant to be out of stock
        $this->blogRepository->update([[
            'id' => $this->mainVariantId,
            'stock' => 0,
            'isCloseout' => true,
        ]], $this->channelContext->getContext());

        $listing = $this->fetchListing();

        // another random variant of the blog should be displayed
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertNotSame($this->mainVariantId, $variantId);
        static::assertContains($variantId, $this->variantIds);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public function testChangeBlogConfigToSingleVariant(): void
    {
        // no main variant will be set initially
        $this->createBlog(['color', 'size'], false);

        // update blog with a main variant
        $this->blogRepository->update([[
            'id' => $this->blogId,
            'variantListingConfig' => [
                'displayParent' => false,
                'mainVariantId' => $this->mainVariantId,
                'configuratorGroupConfig' => [],
            ],
        ]], $this->channelContext->getContext());

        $listing = $this->fetchListing();

        static::assertSame(1, $listing->getTotal());

        // only main variant should be returned
        $mainVariant = $listing->getEntities()->first();
        static::assertNotNull($mainVariant);

        $optionIds = $mainVariant->getOptionIds();
        static::assertNotNull($optionIds);
        static::assertSame($this->mainVariantId, $mainVariant->getId());
        static::assertContains($this->optionIds['red'], $optionIds);
        static::assertContains($this->optionIds['l'], $optionIds);
        static::assertTrue($mainVariant->hasExtension('search'));
    }

    public function testChangeBlogConfigToMainBlog(): void
    {
        // no main variant will be set initially
        $this->createBlog(['color', 'size'], false);

        // update blog with a main variant
        $this->blogRepository->update([
            [
                'id' => $this->blogId,
                'variantListingConfig' => [
                    'displayParent' => true,
                    'mainVariantId' => $this->mainVariantId,
                    'configuratorGroupConfig' => [],
                ],
            ],
        ], $this->channelContext->getContext());

        $listing = $this->fetchListing();

        static::assertSame(1, $listing->getTotal());

        // only main blog should be returned
        $mainBlog = $listing->getEntities()->first();
        static::assertNotNull($mainBlog);

        static::assertSame($this->blogId, $mainBlog->getId());
        static::assertSame($this->mainVariantId, $mainBlog->getVariantListingConfig()?->getMainVariantId());
        static::assertTrue($mainBlog->hasExtension('search'));
    }

    public function testMainBlogIsHiddenIfVariantsOutOfStock(): void
    {
        $this->createBlog([]);

        $this->systemConfigService->set(
            'core.listing.hideCloseoutBlogsWhenOutOfStock',
            true,
            $this->channelContext->getChannelId()
        );

        $this->blogRepository->update([[
            'id' => $this->blogId,
            'displayParent' => true,
            'mainVariantId' => $this->mainVariantId,
            'configuratorGroupConfig' => [],
            'isCloseout' => true,
        ]], $this->channelContext->getContext());

        $variants = array_values(\array_map(static fn ($item) => ['id' => $item, 'stock' => 0], $this->variantIds));

        $this->blogRepository->update($variants, $this->channelContext->getContext());

        $listing = $this->fetchListing();
        static::assertSame(0, $listing->getTotal());
    }

    public function testMainBlogIsHiddenIfAllVariantsDisabled(): void
    {
        $this->createBlog([]);

        $this->blogRepository->update([[
            'id' => $this->blogId,
            'displayParent' => true,
            'mainVariantId' => $this->mainVariantId,
            'configuratorGroupConfig' => [],
        ]], $this->channelContext->getContext());

        $variants = array_values(\array_map(static fn ($item) => ['id' => $item, 'active' => false], $this->variantIds));

        $this->blogRepository->update($variants, $this->channelContext->getContext());

        $listing = $this->fetchListing();
        static::assertSame(0, $listing->getTotal());
    }

    public function testNoListConfig(): void
    {
        $this->createBlog([]);

        $this->blogRepository->update([[
            'id' => $this->blogId,
            'displayParent' => null,
            'mainVariantId' => null,
            'configuratorGroupConfig' => null,
        ]], $this->channelContext->getContext());

        $firstVariant = $this->fetchListing()->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertContains($variantId, $this->variantIds);
    }

    public function testChangeBlogConfigToVariantGroups(): void
    {
        // main variant will be set initially
        $this->createBlog([], true);

        // update blog with no main variant
        $this->blogRepository->update([[
            'id' => $this->blogId,
            'variantListingConfig' => [
                'mainVariantId' => null,
                'configuratorGroupConfig' => $this->getListingConfiguration(['color', 'size']),
            ],
        ]], $this->channelContext->getContext());

        $listing = $this->fetchListing();

        // all variants should be returned
        static::assertSame(4, $listing->getTotal());

        $variants = $listing->getEntities()->getIds();

        static::assertContains($this->variantIds['redXl'], $variants);
        static::assertContains($this->variantIds['redL'], $variants);
        static::assertContains($this->variantIds['greenL'], $variants);
        static::assertContains($this->variantIds['greenXl'], $variants);

        foreach ($listing->getEntities() as $variant) {
            static::assertInstanceOf(BlogEntity::class, $variant);
            static::assertTrue($variant->hasExtension('search'));
        }
    }

    public function testMainVariantAndVariantGroups(): void
    {
        // main variant and variant groups be set initially
        $this->createBlog(['color', 'size'], true);

        $listing = $this->fetchListing();

        // only the main variant should be returned
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertSame($this->mainVariantId, $variantId);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public function testMainVariantAndVariantGroupsWithFilterOnOptions(): void
    {
        // main variant and variant groups be set initially
        $this->createBlog(['color', 'size'], true);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('blog.options.id', $this->optionIds['green']));
        $listing = $this->fetchListing($criteria);

        // only the main variant should be returned
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertSame($this->mainVariantId, $variantId);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public function testDisplayAsGroupFalseReturnsAllMatchingVariantsFromSameDisplayGroup(): void
    {
        $this->createBlog([], true);

        $criteria = new Criteria();
        $criteria->addState(BlogListingLoader::STATE_SKIP_ADD_GROUPING);
        $criteria->addFilter(new EqualsFilter('blog.options.id', $this->optionIds['green']));
        $listing = $this->fetchListing($criteria);

        static::assertSame(2, $listing->getTotal());
        static::assertEqualsCanonicalizing([$this->variantIds['greenL'], $this->variantIds['greenXl']], array_values($listing->getEntities()->getIds()));
    }

    public function testDisplayAsGroupFalseSkipsPreviewRemapping(): void
    {
        $this->createBlog([], true);

        $criteria = new Criteria();
        $criteria->addState(BlogListingLoader::STATE_SKIP_ADD_GROUPING);
        $criteria->addFilter(new EqualsFilter('blog.options.id', $this->optionIds['green']));
        $listing = $this->fetchListing($criteria);

        static::assertSame(2, $listing->getTotal());
        static::assertEqualsCanonicalizing([$this->variantIds['greenL'], $this->variantIds['greenXl']], array_values($listing->getEntities()->getIds()));
    }

    /**
     * @param list<string> $expectedVariantKeys
     */
    #[DataProvider('mainVariantPostFilterProvider')]
    public function testMainVariantAndVariantGroupsWithPostFilterOnOptions(bool $findBestVariant, string $filterOptionKey, array $expectedVariantKeys): void
    {
        $this->createBlog(['color', 'size'], true);

        $this->systemConfigService->set(
            'core.listing.findBestVariant',
            $findBestVariant,
            $this->channelContext->getChannelId()
        );

        $criteria = new Criteria();
        $criteria->addPostFilter(new EqualsFilter('blog.options.id', $this->optionIds[$filterOptionKey]));
        $listing = $this->fetchListing($criteria);

        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);

        $expectedVariants = array_map(fn (string $key) => $this->variantIds[$key], $expectedVariantKeys);
        static::assertContains($firstVariant->getId(), $expectedVariants);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public static function mainVariantPostFilterProvider(): \Generator
    {
        yield 'findBestVariant off, main variant matches filter' => [false, 'l', ['redL']];
        yield 'findBestVariant off, main variant does not match filter' => [false, 'green', ['greenL', 'greenXl']];
        yield 'findBestVariant on, main variant matches filter' => [true, 'l', ['redL']];
        yield 'findBestVariant on, main variant does not match filter' => [true, 'green', ['greenL', 'greenXl']];
    }

    /**
     * @param list<string> $filterOptionKeys
     */
    #[DataProvider('frontendPropertyFilterProvider')]
    public function testMainVariantWithFrontendPropertyFilter(array $filterOptionKeys, string $expectedVariantKey): void
    {
        $this->createBlog(['color', 'size'], true);

        $filters = array_map(fn (string $key) => new OrFilter([
            new EqualsAnyFilter('blog.optionIds', [$this->optionIds[$key]]),
            new EqualsAnyFilter('blog.propertyIds', [$this->optionIds[$key]]),
        ]), $filterOptionKeys);

        $criteria = new Criteria();
        $criteria->addPostFilter(new AndFilter($filters));
        $listing = $this->fetchListing($criteria);

        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        static::assertSame($this->variantIds[$expectedVariantKey], $firstVariant->getId());
    }

    public static function frontendPropertyFilterProvider(): \Generator
    {
        yield 'main variant matches all groups' => [['red', 'l'], 'redL'];
        yield 'main variant matches one of two groups' => [['green', 'l'], 'greenL'];
    }

    public function testMainVariantAndParentWithPostFilterOnOptions(): void
    {
        $this->createBlog(['color', 'size'], true);

        $ids = new IdsCollection();
        $parentBlog = (new BlogBuilder($ids, 'parent-blog'))
            ->price(10)
            ->visibility($this->channelContext->getChannelId())
            ->variantListingConfig(['displayParent' => true])
            ->variant(['id' => $ids->get('parent-blog.green'), 'blogNumber' => 'parent-blog.green', 'stock' => 10, 'options' => [['id' => $this->optionIds['green']]]])
            ->variant(['id' => $ids->get('parent-blog.red'), 'blogNumber' => 'parent-blog.red', 'stock' => 10, 'options' => [['id' => $this->optionIds['red']]]])
            ->build();
        $this->blogRepository->create([$parentBlog], $this->channelContext->getContext());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('blog.parentId', [$this->blogId, $ids->get('parent-blog')]));
        $criteria->addPostFilter(new EqualsFilter('blog.options.id', $this->optionIds['green']));
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), $this->channelContext->getChannelId());
        $listing = $this->blogListingLoader->load($criteria, $context);

        static::assertSame(2, $listing->getTotal());

        $foundIds = array_values($listing->getEntities()->getIds());
        static::assertContains($ids->get('parent-blog'), $foundIds);
        static::assertNotContains($this->mainVariantId, $foundIds);
        static::assertCount(1, array_intersect([$this->variantIds['greenL'], $this->variantIds['greenXl']], $foundIds));
    }

    public function testMainVariantWithPriceFilter(): void
    {
        $this->createBlog(['color', 'size'], true);

        $this->blogRepository->update([
            [
                'id' => $this->variantIds['greenL'],
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 50, 'net' => 42, 'linked' => true],
                ],
            ],
        ], $this->channelContext->getContext());

        $criteria = new Criteria();
        $criteria->addPostFilter(new RangeFilter('blog.cheapestPrice', [RangeFilter::GTE => 40]));
        $listing = $this->fetchListing($criteria);

        static::assertSame(1, $listing->getTotal());
        static::assertSame($this->variantIds['greenL'], $listing->getEntities()->first()?->getId());
    }

    #[DataProvider('findBestVariantProvider')]
    public function testMainBlogAndVariantGroupsWithPostFilterOnOptionsShowsParent(bool $findBestVariant): void
    {
        $this->createBlog(['color', 'size'], false);

        $this->blogRepository->update([
            [
                'id' => $this->blogId,
                'variantListingConfig' => [
                    'displayParent' => true,
                    'mainVariantId' => $this->mainVariantId,
                    'configuratorGroupConfig' => [],
                ],
            ],
        ], $this->channelContext->getContext());

        $this->systemConfigService->set(
            'core.listing.findBestVariant',
            $findBestVariant,
            $this->channelContext->getChannelId()
        );

        $criteria = new Criteria();
        $criteria->addPostFilter(new EqualsFilter('blog.options.id', $this->optionIds['green']));
        $listing = $this->fetchListing($criteria);

        static::assertSame(1, $listing->getTotal());

        $foundBlog = $listing->getEntities()->first();
        static::assertNotNull($foundBlog);
        static::assertSame($this->blogId, $foundBlog->getId());
        static::assertTrue($foundBlog->hasExtension('search'));
    }

    public static function findBestVariantProvider(): \Generator
    {
        yield 'findBestVariant off' => [false];
        yield 'findBestVariant on' => [true];
    }

    public function testPostFilterOnOptionsWithoutMainVariantShowsFilteredVariant(): void
    {
        $this->createBlog([], false);

        $criteria = new Criteria();
        $criteria->addPostFilter(new EqualsFilter('blog.options.id', $this->optionIds['green']));
        $listing = $this->fetchListing($criteria);

        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        $expectedVariants = [$this->variantIds['greenL'], $this->variantIds['greenXl']];
        static::assertContains($variantId, $expectedVariants);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    public static function searchStatesProvider(): \Generator
    {
        yield [ResolvedCriteriaBlogSearchRoute::STATE];
        yield [BlogSuggestRoute::STATE];
    }

    #[DataProvider('searchStatesProvider')]
    public function testVariantOnSearchResult(string $state): void
    {
        $this->createBlog([], true);

        $criteria = new Criteria();
        $criteria->addState($state);

        $this->systemConfigService->set(
            'core.listing.findBestVariant',
            true,
            $this->channelContext->getChannelId()
        );

        $listing = $this->fetchListing($criteria, 'greenL');

        // only the main variant should be returned
        static::assertSame(1, $listing->getTotal());

        $firstVariant = $listing->getEntities()->first();
        static::assertNotNull($firstVariant);
        $variantId = $firstVariant->getId();

        static::assertNotSame($this->variantIds['greenL'], $this->mainVariantId);
        static::assertSame($this->variantIds['greenL'], $variantId);
        static::assertTrue($firstVariant->hasExtension('search'));
    }

    #[DataProvider('searchStatesProvider')]
    public function testPostFilterOnOptionsOnSearchResultRespectsFindBestVariantConfig(string $state): void
    {
        $this->createBlog(['color', 'size'], true);

        $this->systemConfigService->set(
            'core.listing.findBestVariant',
            false,
            $this->channelContext->getChannelId()
        );

        $listing = $this->fetchListing($this->createSearchCriteriaWithOptionFilter($state, 'l'), 'greenL');

        static::assertSame(1, $listing->getTotal());

        $foundBlog = $listing->getEntities()->first();
        static::assertInstanceOf(ChannelBlogEntity::class, $foundBlog);
        static::assertSame($this->mainVariantId, $foundBlog->getId());
        static::assertTrue($foundBlog->hasExtension('search'));

        $listing = $this->fetchListing($this->createSearchCriteriaWithOptionFilter($state, 'green'), 'greenL');

        static::assertSame(1, $listing->getTotal());

        $foundBlog = $listing->getEntities()->first();
        static::assertInstanceOf(ChannelBlogEntity::class, $foundBlog);
        static::assertContains($foundBlog->getId(), [$this->variantIds['greenL'], $this->variantIds['greenXl']]);
        static::assertTrue($foundBlog->hasExtension('search'));

        $this->systemConfigService->set(
            'core.listing.findBestVariant',
            true,
            $this->channelContext->getChannelId()
        );

        $listing = $this->fetchListing($this->createSearchCriteriaWithOptionFilter($state, 'l'), 'greenL');

        static::assertSame(1, $listing->getTotal());

        $foundBlog = $listing->getEntities()->first();
        static::assertInstanceOf(ChannelBlogEntity::class, $foundBlog);
        static::assertSame($this->variantIds['greenL'], $foundBlog->getId());
        static::assertTrue($foundBlog->hasExtension('search'));
    }

    public function testLoadPreviewsOnSearchPage(): void
    {
        $this->systemConfigService->set(
            'core.listing.findBestVariant',
            false,
            $this->channelContext->getChannelId()
        );

        // no main variant will be set initially
        $this->createBlog(['color', 'size']);

        // update blog with a main variant
        $this->blogRepository->update([
            [
                'id' => $this->blogId,
                'variantListingConfig' => [
                    'displayParent' => true,
                    'mainVariantId' => $this->mainVariantId,
                    'configuratorGroupConfig' => [],
                ],
            ],
        ], $this->channelContext->getContext());

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaBlogSearchRoute::STATE);
        $listing = $this->fetchListing($criteria, 'greenL');

        $foundBlog = $listing->getEntities()->first();

        static::assertSame(1, $listing->getTotal());

        static::assertInstanceOf(ChannelBlogEntity::class, $foundBlog);
        static::assertSame($this->blogId, $foundBlog->getId());
        static::assertSame($this->mainVariantId, $foundBlog->getVariantListingConfig()?->getMainVariantId());
        static::assertTrue($foundBlog->hasExtension('search'));

        $this->systemConfigService->set(
            'core.listing.findBestVariant',
            true,
            $this->channelContext->getChannelId()
        );

        $listing = $this->fetchListing($criteria, 'greenL');

        static::assertSame(1, $listing->getTotal());

        $foundBlog = $listing->getEntities()->first();

        static::assertInstanceOf(ChannelBlogEntity::class, $foundBlog);
        static::assertSame($this->variantIds['greenL'], $foundBlog->getId());
        static::assertSame($this->mainVariantId, $foundBlog->getVariantListingConfig()?->getMainVariantId());
        static::assertTrue($foundBlog->hasExtension('search'));
    }

    public function testAllVariants(): void
    {
        $this->createBlog(['size', 'color'], false);

        $listing = $this->fetchListing();

        // all variants should be returned
        static::assertSame(4, $listing->getTotal());

        $variants = $listing->getEntities()->getIds();

        static::assertContains($this->variantIds['redXl'], $variants);
        static::assertContains($this->variantIds['redL'], $variants);
        static::assertContains($this->variantIds['greenL'], $variants);
        static::assertContains($this->variantIds['greenXl'], $variants);

        foreach ($listing->getEntities() as $variant) {
            static::assertTrue($variant->hasExtension('search'));
        }
    }

    public function testMainVariantHasScoreInSearchExtension(): void
    {
        $this->createBlog([], true);

        $listing = $this->fetchListing();

        static::assertSame(1, $listing->getTotal());

        $mainVariant = $listing->getEntities()->first();
        static::assertNotNull($mainVariant);

        static::assertSame($this->mainVariantId, $mainVariant->getId());
        static::assertContains($this->optionIds['red'], $mainVariant->getOptionIds() ?: []);
        static::assertContains($this->optionIds['l'], $mainVariant->getOptionIds() ?: []);
        static::assertTrue($mainVariant->hasExtension('search'));

        $searchData = $mainVariant->get('search');
        static::assertInstanceOf(ArrayEntity::class, $searchData);
        static::assertTrue($searchData->get('_score') > 0);
    }

    private function createSearchCriteriaWithOptionFilter(string $state, string $optionKey): Criteria
    {
        $criteria = new Criteria();
        $criteria->addState($state);
        $criteria->addPostFilter(new EqualsFilter('blog.options.id', $this->optionIds[$optionKey]));

        return $criteria;
    }

    /**
     * @return EntitySearchResult<BlogCollection>
     */
    private function fetchListing(?Criteria $criteria = null, string $term = 'example'): EntitySearchResult
    {
        if (!$criteria instanceof Criteria) {
            $criteria = new Criteria();
        }

        $criteria->addFilter(new EqualsFilter('blog.parentId', $this->blogId));
        $criteria->setTerm($term);

        return $this->blogListingLoader->load($criteria, $this->channelContext);
    }

    /**
     * @param array<string> $listingProperties
     */
    private function createBlog(array $listingProperties, bool $hasMainVariant = false): void
    {
        $this->blogId = Uuid::randomHex();

        $this->optionIds = [
            'red' => Uuid::randomHex(),
            'green' => Uuid::randomHex(),
            'xl' => Uuid::randomHex(),
            'l' => Uuid::randomHex(),
        ];

        $this->variantIds = [
            'redXl' => Uuid::randomHex(),
            'greenXl' => Uuid::randomHex(),
            'redL' => Uuid::randomHex(),
            'greenL' => Uuid::randomHex(),
        ];

        $this->variantIds['mainVariantId'] = $this->variantIds['redL'];

        $this->groupIds = [
            'color' => Uuid::randomHex(),
            'size' => Uuid::randomHex(),
        ];

        $this->mainVariantId = $this->variantIds['redL'];

        $config = $this->getListingConfiguration($listingProperties);

        $tax = ['id' => Uuid::randomHex(), 'name' => '19', 'taxRate' => 19];

        $data = [
            [
                'id' => $this->blogId,
                'variantListingConfig' => [
                    'displayParent' => null,
                    'mainVariantId' => null,
                    'configuratorGroupConfig' => $config,
                ],
                'blogNumber' => 'a.0',
                'manufacturer' => ['name' => 'test'],
                'tax' => $tax,
                'stock' => 10,
                'name' => 'example',
                'active' => true,
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true],
                ],
                'configuratorSettings' => [
                    [
                        'option' => [
                            'id' => $this->optionIds['red'],
                            'name' => 'Red',
                            'group' => [
                                'id' => $this->groupIds['color'],
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['green'],
                            'name' => 'Green',
                            'group' => [
                                'id' => $this->groupIds['color'],
                                'name' => 'Color',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['xl'],
                            'name' => 'XL',
                            'group' => [
                                'id' => $this->groupIds['size'],
                                'name' => 'size',
                            ],
                        ],
                    ],
                    [
                        'option' => [
                            'id' => $this->optionIds['l'],
                            'name' => 'L',
                            'group' => [
                                'id' => $this->groupIds['size'],
                                'name' => 'size',
                            ],
                        ],
                    ],
                ],
                'visibilities' => [
                    [
                        'channelId' => $this->channelContext->getChannelId(),
                        'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
            [
                'id' => $this->variantIds['redXl'],
                'blogNumber' => 'a.1',
                'stock' => 10,
                'name' => 'example redXl',
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['xl']],
                ],
            ],
            [
                'id' => $this->variantIds['greenXl'],
                'blogNumber' => 'a.3',
                'stock' => 10,
                'name' => 'example greenXl',
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['xl']],
                ],
            ],
            [
                'id' => $this->variantIds['redL'],
                'blogNumber' => 'a.5',
                'stock' => 10,
                'name' => 'example redL',
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['red']],
                    ['id' => $this->optionIds['l']],
                ],
            ],
            [
                'id' => $this->variantIds['greenL'],
                'blogNumber' => 'a.7',
                'stock' => 10,
                'name' => 'example greenL',
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['l']],
                ],
            ],
        ];

        $this->addTaxDataToChannel($this->channelContext, $tax);

        $this->blogRepository->create($data, $this->channelContext->getContext());

        if ($hasMainVariant) {
            // Update parent blog, configure to use selected mainVariantId in listing config
            $this->blogRepository->update([
                [
                    'id' => $this->blogId,
                    'variantListingConfig' => [
                        'displayParent' => null,
                        'mainVariantId' => $this->mainVariantId,
                        'configuratorGroupConfig' => $config,
                    ],
                ],
            ], $this->channelContext->getContext());
        }
    }

    /**
     * @param array<string> $listingProperties
     *
     * @return array<int, array<string, string|true>>
     */
    private function getListingConfiguration(array $listingProperties): array
    {
        $config = [];

        foreach ($listingProperties as $groupName) {
            $config[] = [
                'id' => $this->groupIds[$groupName],
                'expressionForListings' => true,
                'representation' => 'box', // box, select, image, color
            ];
        }

        return $config;
    }
}
