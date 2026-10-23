<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel\CrossSelling;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingDefinition;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Events\BlogCrossSellingIdsCriteriaEvent;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\CrossSelling\AbstractBlogCrossSellingRoute;
use Contena\Core\Content\Blog\Channel\CrossSelling\BlogCrossSellingRoute;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter\ContentStreamFilterCollection;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilderInterface;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TaxAddToChannelTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelEntity;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Generator;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Group('store-api')]
class CrossSellingRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;
    use TaxAddToChannelTestBehaviour;

    private ChannelContext $channelContext;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private AbstractBlogCrossSellingRoute $route;

    private KernelBrowser $browser;

    protected function setUp(): void
    {
        $this->channelContext = Generator::generateChannelContext(channel: (new ChannelEntity())->assign([
            'id' => TestDefaults::CHANNEL,
            'taxCalculationType' => ChannelDefinition::CALCULATION_TYPE_VERTICAL,
        ]));
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->route = static::getContainer()->get(BlogCrossSellingRoute::class);

        $this->browser = $this->createCustomChannelBrowser([
            'id' => TestDefaults::CHANNEL,
            'languages' => [],
        ]);
    }

    public function testLoad(): void
    {
        $blogId = Uuid::randomHex();

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'contentStreamId' => $this->createBlogStream(),
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $result = $this->route->load($blogId, new Request(), $this->channelContext, new Criteria())
            ->getResult();

        static::assertCount(1, $result);

        $element = $result->first();
        static::assertNotNull($element);
        static::assertSame(3, $element->getTotal());
        static::assertSame('Test Cross Selling', $element->getCrossSelling()->getName());

        $lastPrice = 0;
        foreach ($element->getBlogs() as $blog) {
            $blogPrice = $blog->getCurrencyPrice(Defaults::CURRENCY);
            static::assertNotNull($blogPrice);
            static::assertGreaterThanOrEqual($lastPrice, $blogPrice->getGross());
            $lastPrice = $blogPrice->getGross();
        }
    }

    public function testLoadForBlog(): void
    {
        $blogId = Uuid::randomHex();

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'contentStreamId' => $this->createBlogStream(),
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $this->channelContext->getContext())->getEntities()->get($blogId);
        static::assertInstanceOf(BlogEntity::class, $blog);
        $result = $this->route->load($blog->getId(), new Request(), $this->channelContext, new Criteria())->getResult();

        static::assertCount(1, $result);

        $element = $result->first();
        static::assertNotNull($element);
        static::assertSame(3, $element->getTotal());
        static::assertSame('Test Cross Selling', $element->getCrossSelling()->getName());

        $lastPrice = 0;
        foreach ($element->getBlogs() as $crossSellingBlog) {
            $blogPrice = $crossSellingBlog->getCurrencyPrice(Defaults::CURRENCY);
            static::assertNotNull($blogPrice);
            static::assertGreaterThanOrEqual($lastPrice, $blogPrice->getGross());
            $lastPrice = $blogPrice->getGross();
        }
    }

    public function testLoadWithPartialDataLoadingEnabled(): void
    {
        // Regression test for https://github.com/contena-cms/contena/issues/18587.
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.partialDataLoading', true);

        $blogId = Uuid::randomHex();

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'contentStreamId' => $this->createBlogStream(),
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $result = $this->route->load($blogId, new Request(), $this->channelContext, new Criteria())->getResult();

        $element = $result->first();
        static::assertNotNull($element);
        static::assertSame(3, $element->getTotal());
        static::assertCount(3, $element->getBlogs());

        foreach ($element->getBlogs() as $crossSellingBlog) {
            static::assertSame('Test', $crossSellingBlog->getName());
            static::assertNotNull($crossSellingBlog->getCurrencyPrice(Defaults::CURRENCY));
        }
    }

    public function testLoadIgnoresPartialFieldSelectionOfTheRequest(): void
    {
        $blogId = Uuid::randomHex();

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'contentStreamId' => $this->createBlogStream(),
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $this->browser->request('POST', $this->getUrl($blogId), ['fields' => ['id', 'name']]);

        $response = $this->browser->getResponse();
        static::assertSame(200, $response->getStatusCode(), (string) $response->getContent());

        $content = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $content);
        static::assertArrayHasKey('blogs', $content[0]);
        static::assertCount(3, $content[0]['blogs']);
        static::assertArrayHasKey('blogNumber', $content[0]['blogs'][0]);
    }

    public function testLoadForBlogWithCloseoutAndFilterDisabled(): void
    {
        // disable hideCloseoutBlogsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', false);

        $blogId = Uuid::randomHex();

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'contentStreamId' => $this->createBlogStream(true),
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $this->channelContext->getContext())->getEntities()->get($blogId);
        static::assertInstanceOf(BlogEntity::class, $blog);
        $result = $this->route->load($blog->getId(), new Request(), $this->channelContext, new Criteria())->getResult();

        static::assertCount(1, $result);

        $element = $result->first();
        static::assertNotNull($element);
        static::assertSame(3, $element->getTotal());
        static::assertSame('Test Cross Selling', $element->getCrossSelling()->getName());

        $lastPrice = 0;
        foreach ($element->getBlogs() as $crossSellingBlog) {
            $blogPrice = $crossSellingBlog->getCurrencyPrice(Defaults::CURRENCY);
            static::assertNotNull($blogPrice);
            static::assertGreaterThanOrEqual($lastPrice, $blogPrice->getGross());
            $lastPrice = $blogPrice->getGross();
        }
    }

    public function testLoadForBlogWithCloseoutAndFilterEnabled(): void
    {
        // enable hideCloseoutBlogsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', true);

        $blogId = Uuid::randomHex();

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'contentStreamId' => $this->createBlogStream(true),
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $this->channelContext->getContext())->getEntities()->get($blogId);
        static::assertInstanceOf(BlogEntity::class, $blog);
        $result = $this->route->load($blog->getId(), new Request(), $this->channelContext, new Criteria())->getResult();

        static::assertCount(1, $result);

        $element = $result->first();
        static::assertNotNull($element);
        static::assertSame(1, $element->getTotal());
        static::assertSame('Test Cross Selling', $element->getCrossSelling()->getName());

        $lastPrice = 0;
        foreach ($element->getBlogs() as $crossSellingBlog) {
            $blogPrice = $crossSellingBlog->getCurrencyPrice(Defaults::CURRENCY);
            static::assertNotNull($blogPrice);
            static::assertGreaterThanOrEqual($lastPrice, $blogPrice->getGross());
            $lastPrice = $blogPrice->getGross();
        }
    }

    public function testLoadForBlogWithCloseoutAndFilterEnabledAllBlogsOfOfStock(): void
    {
        // enable hideCloseoutBlogsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', true);

        $blogId = Uuid::randomHex();

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'contentStreamId' => $this->createBlogStream(true, true),
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $this->channelContext->getContext())->getEntities()->get($blogId);
        static::assertInstanceOf(BlogEntity::class, $blog);
        $result = $this->route->load($blog->getId(), new Request(), $this->channelContext, new Criteria())->getResult();

        static::assertCount(1, $result);
    }

    public function testLoadForBlogWithBlogCrossSellingAssignedBlogs(): void
    {
        // enable hideCloseoutBlogsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', false);

        $blogId = Uuid::randomHex();

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'type' => 'blogList',
            'assignedBlogs' => $this->createAssignedBlogs(true, true),
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $this->channelContext->getContext())->getEntities()->get($blogId);
        static::assertInstanceOf(BlogEntity::class, $blog);
        $result = $this->route->load($blog->getId(), new Request(), $this->channelContext, new Criteria())->getResult();

        static::assertCount(1, $result);

        $element = $result->first();
        static::assertNotNull($element);
        static::assertCount(5, $element->getBlogs());
        static::assertNotNull($element->getCrossSelling()->getAssignedBlogs());
        static::assertCount(5, $element->getCrossSelling()->getAssignedBlogs());

        $this->browser->request(
            'POST',
            $this->getUrl($blogId),
            [
                'includes' => [
                    'blog' => ['id', 'name'],
                    'blog_cross_selling' => ['id', 'name'],
                ],
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $response);
        static::assertArrayHasKey('crossSelling', $response[0]);
        static::assertArrayHasKey('name', $response[0]['crossSelling']);
        static::assertArrayHasKey('id', $response[0]['crossSelling']);
        static::assertSame('Test Cross Selling', $response[0]['crossSelling']['name']);

        $expected = ['id', 'name', 'apiAlias'];
        sort($expected);

        static::assertIsArray($response[0]['crossSelling']);
        $properties = array_keys($response[0]['crossSelling']);
        sort($properties);
        static::assertSame($expected, $properties);

        static::assertArrayHasKey('blogs', $response[0]);
        static::assertCount(5, $response[0]['blogs']);

        $properties = array_keys($response[0]['blogs'][0]);
        sort($properties);
        static::assertSame($expected, $properties);
    }

    public function testLoadForBlogWithBlogCrossSellingAssignedBlogsFiltersHiddenBlogs(): void
    {
        $blogId = Uuid::randomHex();
        $visibleBlog = $this->getBlogData();
        $hiddenBlog = $this->getBlogData();
        $hiddenBlog['visibilities'] = [
            ['channelId' => $this->channelContext->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_LINK],
        ];

        $this->blogRepository->create([$visibleBlog, $hiddenBlog], $this->channelContext->getContext());

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'type' => 'blogList',
            'assignedBlogs' => [
                ['blogId' => $visibleBlog['id'], 'position' => 1],
                ['blogId' => $hiddenBlog['id'], 'position' => 2],
            ],
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $result = $this->route->load($blogId, new Request(), $this->channelContext, new Criteria())->getResult();

        $element = $result->first();
        static::assertNotNull($element);
        static::assertSame([$visibleBlog['id']], array_values(array_unique($element->getBlogs()->getIds())));
        static::assertNotNull($element->getCrossSelling()->getAssignedBlogs());
        static::assertCount(2, $element->getCrossSelling()->getAssignedBlogs());
    }

    /**
     * @param list<array{active: bool, closeout: bool, stock: int}> $variants
     */
    #[DataProvider('assignedParentProvider')]
    public function testLoadForBlogWithBlogCrossSellingAssignedParent(array $variants, bool $hideCloseoutBlogs, bool $expectParent): void
    {
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', $hideCloseoutBlogs);

        $ids = new IdsCollection();
        $channelId = TestDefaults::CHANNEL;

        $parent = (new BlogBuilder($ids, 'parent'))
            ->price(10)
            ->visibility($channelId);

        foreach ($variants as $index => $variant) {
            $parent->variant(
                (new BlogBuilder($ids, 'variant-' . $index))
                    ->price(10)
                    ->active($variant['active'])
                    ->closeout($variant['closeout'])
                    ->stock($variant['stock'])
                    ->build()
            );
        }

        $main = (new BlogBuilder($ids, 'main'))->price(10)->visibility($channelId)->build();
        $main['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'active' => true,
            'type' => BlogCrossSellingDefinition::TYPE_BLOG_LIST,
            'assignedBlogs' => [
                ['blogId' => $ids->get('parent'), 'position' => 1],
                ['blogId' => $ids->get('simple'), 'position' => 2],
            ],
        ]];

        $this->blogRepository->create([
            $parent->build(),
            (new BlogBuilder($ids, 'simple'))->price(10)->visibility($channelId)->build(),
        ], Context::createDefaultContext());
        $this->blogRepository->create([$main], Context::createDefaultContext());

        // variants inherit the visibilities of the parent, which requires a context that considers inheritance
        $context = static::getContainer()->get(ChannelContextFactory::class)->create(Uuid::randomHex(), $channelId);

        $result = $this->route->load($ids->get('main'), new Request(), $context, new Criteria())->getResult();

        $element = $result->first();
        static::assertNotNull($element);

        $expected = $expectParent ? [$ids->get('parent'), $ids->get('simple')] : [$ids->get('simple')];
        static::assertSame($expected, array_values($element->getBlogs()->getIds()));
        static::assertSame(\count($expected), $element->getTotal());
    }

    /**
     * @return iterable<string, array{list<array{active: bool, closeout: bool, stock: int}>, bool, bool}>
     */
    public static function assignedParentProvider(): iterable
    {
        yield 'all variants inactive' => [
            [['active' => false, 'closeout' => false, 'stock' => 10], ['active' => false, 'closeout' => false, 'stock' => 10]],
            false,
            false,
        ];

        yield 'one variant active' => [
            [['active' => false, 'closeout' => false, 'stock' => 10], ['active' => true, 'closeout' => false, 'stock' => 10]],
            false,
            true,
        ];

        yield 'all variants out-of-stock closeouts with hidden closeouts' => [
            [['active' => true, 'closeout' => true, 'stock' => 0], ['active' => true, 'closeout' => true, 'stock' => 0]],
            true,
            false,
        ];

        yield 'all variants out-of-stock closeouts with visible closeouts' => [
            [['active' => true, 'closeout' => true, 'stock' => 0], ['active' => true, 'closeout' => true, 'stock' => 0]],
            false,
            true,
        ];

        yield 'one closeout variant in stock with hidden closeouts' => [
            [['active' => true, 'closeout' => true, 'stock' => 0], ['active' => true, 'closeout' => true, 'stock' => 5]],
            true,
            true,
        ];
    }

    public function testLoadForBlogWithBlogCrossSellingAssignedBlogsOutOfStock(): void
    {
        // enable hideCloseoutBlogsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', true);

        $blogId = Uuid::randomHex();

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'type' => 'blogList',
            'assignedBlogs' => $this->createAssignedBlogs(true, true),
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $this->channelContext->getContext())->getEntities()->get($blogId);
        static::assertInstanceOf(BlogEntity::class, $blog);
        $result = $this->route->load($blog->getId(), new Request(), $this->channelContext, new Criteria())->getResult();

        static::assertCount(1, $result);

        $element = $result->first();
        static::assertNotNull($element);
        static::assertCount(0, $element->getBlogs());
        static::assertNotNull($element->getCrossSelling()->getAssignedBlogs());
        static::assertCount(5, $element->getCrossSelling()->getAssignedBlogs());
    }

    /**
     * Shouldn't be necessary to test for loadForBlogs() as the caller has to handle sorting of cross sellings
     */
    public function testLoadMultipleCrossSellingsOrderedByPosition(): void
    {
        $blogId = Uuid::randomHex();

        $crossSellingIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'id' => $crossSellingIds[0],
            'name' => 'First Cross Selling',
            'position' => 1,
            'active' => true,
            'contentStreamId' => $this->createBlogStream(),
        ], [
            'id' => $crossSellingIds[1],
            'name' => 'Second Cross Selling',
            'position' => 2,
            'active' => true,
            'contentStreamId' => $this->createBlogStream(),
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $result = $this->route->load($blogId, new Request(), $this->channelContext, new Criteria())->getResult();

        static::assertCount(2, $result);
        foreach ($result as $index => $element) {
            static::assertSame($crossSellingIds[$index], $element->getCrossSelling()->getId());
        }
    }

    /**
     * Shouldn't be necessary to test for loadForBlogs() as the caller has to handle cross selling inheritance loading
     */
    public function testLoadCrossSellingsForVariantInheritedByParent(): void
    {
        $blogId = Uuid::randomHex();
        $optionId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $crossSellingIds = [
            Uuid::randomHex(),
            Uuid::randomHex(),
        ];
        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'id' => $crossSellingIds[0],
            'name' => 'First Cross Selling',
            'position' => 1,
            'active' => true,
            'contentStreamId' => $this->createBlogStream(),
        ], [
            'id' => $crossSellingIds[1],
            'name' => 'Second Cross Selling',
            'position' => 2,
            'active' => true,
            'contentStreamId' => $this->createBlogStream(),
        ]];
        $blogData['configuratorSettings'] = [[
            'option' => [
                'id' => $optionId,
                'name' => 'Option',
                'position' => 0,
                'group' => [
                    'sortingType' => 'alphanumeric',
                    'displayType' => 'text',
                    'name' => 'test one group',
                ],
            ],
            'position' => 0,
        ]];
        $blogData['children'] = [[
            'id' => $variantId,
            'type' => BlogDefinition::TYPE_PHYSICAL,
            'blogNumber' => Uuid::randomHex(),
            'stock' => 1,
            'options' => [
                [
                    'id' => $optionId,
                ],
            ],
        ]];

        $this->channelContext->getContext()->setConsiderInheritance(true);
        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $result = $this->route->load($variantId, new Request(), $this->channelContext, new Criteria())->getResult();

        static::assertCount(2, $result);
        foreach ($result as $index => $element) {
            static::assertSame($crossSellingIds[$index], $element->getCrossSelling()->getId());
        }
    }

    public function testCrossSellingBlogStreamNotContainsCurrentVariantAndItsSiblings(): void
    {
        $parentId = Uuid::randomHex();
        $variantIds = [Uuid::randomHex(), Uuid::randomHex()];
        $otherBlogId = Uuid::randomHex();
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $streamId = Uuid::randomHex();
        $optionIds = [Uuid::randomHex(), Uuid::randomHex()];
        $groupId = Uuid::randomHex();

        $parentData = $this->getBlogData($parentId, $manufacturerId, $taxId);
        $parentData['configuratorSettings'] = [];
        $parentData['children'] = [];

        foreach ($optionIds as $index => $optionId) {
            $parentData['configuratorSettings'][] = [
                'option' => [
                    'id' => $optionId,
                    'name' => 'Option ' . $index,
                    'position' => $index,
                    'group' => [
                        'id' => $groupId,
                        'sortingType' => 'alphanumeric',
                        'displayType' => 'text',
                        'name' => 'test one group',
                    ],
                ],
                'position' => $index,
            ];
            $parentData['children'][] = [
                'id' => $variantIds[$index],
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'options' => [['id' => $optionId]],
            ];
        }

        $parentData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'contentStreamId' => $streamId,
        ]];

        // dynamic blog group which contains the whole variant family and one unrelated blog
        static::getContainer()->get('content_stream.repository')->create([
            [
                'id' => $streamId,
                'name' => 'testStream',
                'filters' => [
                    [
                        'type' => 'equalsAny',
                        'field' => 'id',
                        'value' => implode('|', [$parentId, ...$variantIds, $otherBlogId]),
                    ],
                ],
            ],
        ], $this->channelContext->getContext());

        $this->blogRepository->create(
            [$parentData, $this->getBlogData($otherBlogId, $manufacturerId, $taxId)],
            $this->channelContext->getContext()
        );

        $this->channelContext->getContext()->setConsiderInheritance(true);

        $result = $this->route->load($variantIds[0], new Request(), $this->channelContext, new Criteria())
            ->getResult();

        $element = $result->first();

        static::assertNotNull($element);
        static::assertSame(
            [$otherBlogId],
            array_values($element->getBlogs()->getIds()),
            'Neither the currently viewed variant nor its parent or sibling variants may be cross-sold.'
        );
    }

    public function testCrossSellingEventSubscriberCanUpdateCriteria(): void
    {
        $eventDispatcher = new EventDispatcher();
        $blogRepository = static::getContainer()->get('blog.repository');
        $eventDispatcher->addListener(
            BlogCrossSellingIdsCriteriaEvent::class,
            static function (BlogCrossSellingIdsCriteriaEvent $event) use ($blogRepository): void {
                $ids = array_values($event->getCrossSelling()->getAssignedBlogs()?->getBlogIds() ?? []);

                $criteria = new Criteria();
                $criteria->addFilter(new EqualsAnyFilter('parentId', $ids));
                $crossSellingBlogs = $blogRepository->searchIds($criteria, $event->getContext())->getIds();
                $event->getCriteria()->setIds($crossSellingBlogs);
            }
        );

        $route = new BlogCrossSellingRoute(
            static::getContainer()->get('blog_cross_selling.repository'),
            $eventDispatcher,
            static::createStub(ContentStreamBuilderInterface::class),
            static::getContainer()->get('channel.blog.repository'),
            static::createStub(SystemConfigService::class),
            static::createStub(BlogListingLoader::class),
            static::createStub(AbstractBlogCloseoutFilterFactory::class),
            static::createStub(CacheTagCollector::class),
            static::getContainer()->get(Connection::class),
            new ExtensionDispatcher(new EventDispatcher()),
            static::getContainer()->get('logger'),
        );

        $blogId = Uuid::randomHex();

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 3,
            'type' => 'blogList',
            'assignedBlogs' => $this->createAssignedBlogs(true, false, true),
        ]];

        $this->channelContext->getContext()->setConsiderInheritance(true);
        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $blog = $this->blogRepository->search(new Criteria([$blogId]), $this->channelContext->getContext())->getEntities()->get($blogId);
        static::assertInstanceOf(BlogEntity::class, $blog);
        $result = $route->load($blog->getId(), new Request(), $this->channelContext, new Criteria())->getResult();
        static::assertCount(1, $result);

        $element = $result->first();
        static::assertNotNull($element);
        static::assertCount(5, $element->getBlogs());
        static::assertNotNull($element->getCrossSelling()->getAssignedBlogs());
        static::assertCount(5, $element->getCrossSelling()->getAssignedBlogs());
    }

    public function testCrossSellingBlogStreamNotContainsBlog(): void
    {
        $blogId = Uuid::randomHex();

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'contentStreamId' => $this->createBlogStream(false, false, $blogId),
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $result = $this->route->load($blogId, new Request(), $this->channelContext, new Criteria())
            ->getResult();

        $element = $result->first();

        static::assertNotNull($element);
        static::assertCount(5, $element->getBlogs());
        static::assertNotContains($blogId, $element->getBlogs()->getIds());
    }

    public function testLoadSelectsAllBlogsForADynamicBlogGroupWithOnlyAnEmptyCondition(): void
    {
        $blogId = Uuid::randomHex();
        $streamId = Uuid::randomHex();
        $orContainerId = Uuid::randomHex();
        $andContainerId = Uuid::randomHex();
        $otherBlogIds = array_column($this->createBlogs(), 'id');

        // "Blog" "is equal to any of" without a blog, as saved by the Administration
        static::getContainer()->get('content_stream.repository')->create([
            [
                'id' => $streamId,
                'name' => 'Empty dynamic blog group',
                'filters' => [
                    ['id' => $orContainerId, 'type' => 'multi', 'operator' => 'OR', 'position' => 0],
                    ['id' => $andContainerId, 'parentId' => $orContainerId, 'type' => 'multi', 'operator' => 'AND', 'position' => 0],
                    ['parentId' => $andContainerId, 'type' => 'equalsAny', 'field' => 'id', 'position' => 0],
                ],
            ],
        ], $this->channelContext->getContext());

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Empty Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 100,
            'contentStreamId' => $streamId,
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $element = $this->route->load($blogId, new Request(), $this->channelContext, new Criteria())
            ->getResult()
            ->first();

        static::assertNotNull($element);

        $crossSellingBlogIds = $element->getBlogs()->getIds();
        foreach ($otherBlogIds as $otherBlogId) {
            static::assertContains($otherBlogId, $crossSellingBlogIds);
        }
        static::assertNotContains($blogId, $crossSellingBlogIds);
    }

    public function testLoadShowsNoBlogsForAnInvalidDynamicBlogGroup(): void
    {
        $blogId = Uuid::randomHex();
        $streamId = Uuid::randomHex();
        $this->createBlogs();

        static::getContainer()->get('content_stream.repository')->create([
            ['id' => $streamId, 'name' => 'Dynamic blog group without conditions'],
        ], $this->channelContext->getContext());

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Invalid Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'contentStreamId' => $streamId,
        ]];

        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $element = $this->route->load($blogId, new Request(), $this->channelContext, new Criteria())
            ->getResult()
            ->first();

        static::assertNotNull($element);
        static::assertSame(0, $element->getTotal());
        static::assertCount(0, $element->getBlogs());
    }

    public function testCrossSellingUsingDynamicGroupUpdatesAfterSeparateFilterSync(): void
    {
        $blogId = Uuid::randomHex();
        $streamId = Uuid::randomHex();

        $initialBlogIds = array_column($this->createBlogs(), 'id');
        $replacementBlogIds = array_column($this->createBlogs(), 'id');

        static::getContainer()->get('content_stream.repository')->create([
            [
                'id' => $streamId,
                'name' => 'testStream',
                'filters' => [
                    [
                        'type' => 'equalsAny',
                        'field' => 'id',
                        'value' => implode('|', $initialBlogIds),
                    ],
                ],
            ],
        ], $this->channelContext->getContext());

        $blogData = $this->getBlogData($blogId);
        $blogData['crossSellings'] = [[
            'name' => 'Test Cross Selling',
            'sortBy' => BlogCrossSellingDefinition::SORT_BY_PRICE,
            'sortDirection' => FieldSorting::ASCENDING,
            'active' => true,
            'limit' => 10,
            'contentStreamId' => $streamId,
        ]];
        $this->blogRepository->create([$blogData], $this->channelContext->getContext());

        $result = $this->route->load($blogId, new Request(), $this->channelContext, new Criteria())->getResult();
        $element = $result->first();
        static::assertNotNull($element);
        static::assertEqualsCanonicalizing(
            array_values($initialBlogIds),
            array_values($element->getBlogs()->getIds()),
        );

        $filterId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(id)) FROM content_stream_filter WHERE content_stream_id = :streamId',
            ['streamId' => Uuid::fromHexToBytes($streamId)],
        );
        static::assertIsString($filterId);

        /** @var EntityRepository<ContentStreamFilterCollection> $contentStreamFilterRepository */
        $contentStreamFilterRepository = static::getContainer()->get('content_stream_filter.repository');
        $contentStreamFilterRepository->update([[
            'id' => $filterId,
            'value' => implode('|', $replacementBlogIds),
        ]], Context::createDefaultContext());

        $result = $this->route->load($blogId, new Request(), $this->channelContext, new Criteria())->getResult();
        $element = $result->first();
        static::assertNotNull($element);
        static::assertEqualsCanonicalizing(
            array_values($replacementBlogIds),
            array_values($element->getBlogs()->getIds()),
            'Cross-selling must reflect updated content_stream_filter value even when the parent content_stream is untouched.',
        );
    }

    private function createBlogStream(bool $includesIsCloseoutBlogs = false, bool $noStock = false, ?string $includedBlogId = null): string
    {
        $id = Uuid::randomHex();
        $randomBlogIds = implode('|', array_column($this->createBlogs($includesIsCloseoutBlogs, $noStock), 'id'));

        if ($includedBlogId) {
            $randomBlogIds .= '|' . $includedBlogId;
        }

        static::getContainer()->get('content_stream.repository')->create([
            [
                'id' => $id,
                'filters' => [
                    [
                        'type' => 'equalsAny',
                        'field' => 'id',
                        'value' => $randomBlogIds,
                    ],
                ],
                'name' => 'testStream',
            ],
        ], $this->channelContext->getContext());

        return $id;
    }

    /**
     * @return list<array{blogId: string, position: int}>
     */
    private function createAssignedBlogs(bool $includesIsCloseoutBlogs = false, bool $noStock = false, bool $withChild = false): array
    {
        $assignedBlogs = [];
        $randomBlogIds = array_column($this->createBlogs($includesIsCloseoutBlogs, $noStock, $withChild), 'id');

        foreach ($randomBlogIds as $index => $blogId) {
            $assignedBlogs[] = [
                'blogId' => $blogId,
                'position' => $index + 1,
            ];
        }

        return $assignedBlogs;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createBlogs(bool $isCloseout = false, bool $noStock = false, bool $withChild = false): array
    {
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $blogs = [];

        if ($isCloseout) {
            for ($i = 0; $i < 5; ++$i) {
                if ($noStock) {
                    $stock = 0;
                } else {
                    $stock = $i > 0 ? 0 : 1;
                }

                $blogs[] = $this->getBlogData(null, $manufacturerId, $taxId, $withChild, $stock, $isCloseout);
            }
        } else {
            for ($i = 0; $i < 5; ++$i) {
                $blogs[] = $this->getBlogData(null, $manufacturerId, $taxId, $withChild);
            }
        }

        $this->blogRepository->create($blogs, $this->channelContext->getContext());
        $lastBlog = end($blogs);
        static::assertIsArray($lastBlog);
        $this->addTaxDataToChannel($this->channelContext, $lastBlog['tax']);

        return $blogs;
    }

    /**
     * @return array<string, mixed>
     */
    private function getBlogData(
        ?string $id = null,
        ?string $manufacturerId = null,
        ?string $taxId = null,
        bool $withChild = false,
        int $stock = 1,
        bool $isCloseout = false
    ): array {
        $price = random_int(0, 10);

        $blog = [
            'id' => $id ?? Uuid::randomHex(),
            'type' => BlogDefinition::TYPE_PHYSICAL,
            'blogNumber' => Uuid::randomHex(),
            'stock' => $stock,
            'name' => 'Test',
            'isCloseout' => $isCloseout,
            'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => $price, 'net' => $price, 'linked' => false]],
            'manufacturer' => ['id' => $manufacturerId ?? Uuid::randomHex(), 'name' => 'test'],
            'tax' => ['id' => $taxId ?? Uuid::randomHex(), 'taxRate' => 17, 'name' => 'with id'],
            'visibilities' => [
                ['channelId' => $this->channelContext->getChannelId(), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
            ],
        ];

        if ($withChild) {
            $optionId = Uuid::randomHex();
            $blog['configuratorSettings'] = [[
                'option' => [
                    'id' => $optionId,
                    'name' => 'Option',
                    'position' => 0,
                    'group' => [
                        'sortingType' => 'alphanumeric',
                        'displayType' => 'text',
                        'name' => 'test one group',
                    ],
                ],
                'position' => 0,
            ]];
            $blog['children'] = [[
                'id' => Uuid::randomHex(),
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'options' => [
                    [
                        'id' => $optionId,
                    ],
                ],
            ]];
        }

        $this->addTaxDataToChannel($this->channelContext, $blog['tax']);

        return $blog;
    }

    private function getUrl(string $blogId): string
    {
        return '/store-api/blog/' . $blogId . '/cross-selling';
    }
}
