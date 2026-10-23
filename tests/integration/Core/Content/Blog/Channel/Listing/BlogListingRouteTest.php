<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel\Listing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Category\CategoryCollection;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Events\BlogListingCriteriaEvent;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Content\ContentStream\Aggregate\ContentStreamFilter\ContentStreamFilterCollection;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Util\Base64;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Group('store-api')]
class BlogListingRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private KernelBrowser $browser;

    private IdsCollection $ids;

    private string $blogId;

    /**
     * @var array<string>
     */
    private array $groupIds;

    /**
     * @var array<string>
     */
    private array $optionIds;

    /**
     * @var array<string>
     */
    private array $variantIds;

    /**
     * @var EntityRepository<CategoryCollection>
     */
    private EntityRepository $categoryRepository;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    /**
     * @var EntityRepository<ContentStreamFilterCollection>
     */
    private EntityRepository $contentStreamFilterRepository;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();
        $this->createChannelContext(['id' => $this->ids->create('sales-channel')]);

        /** @var EntityRepository<CategoryCollection> */
        $categoryRepository = static::getContainer()->get('category.repository');
        $this->categoryRepository = $categoryRepository;

        /** @var EntityRepository<BlogCollection> */
        $blogRepository = static::getContainer()->get('blog.repository');
        $this->blogRepository = $blogRepository;

        /** @var EntityRepository<ContentStreamFilterCollection> */
        $contentStreamFilterRepository = static::getContainer()->get('content_stream_filter.repository');
        $this->contentStreamFilterRepository = $contentStreamFilterRepository;
    }

    public function testLoadBlogs(): void
    {
        $this->createData();

        $this->browser->request(
            'POST',
            '/store-api/blog-listing/' . $this->ids->get('category')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('blog_listing', $response['apiAlias']);
        static::assertCount(6, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
    }

    public function testCustomListingParametersOfTheCompressedCriteriaAreReadLikeQueryParameters(): void
    {
        $this->createData();

        $queries = [];
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            BlogListingCriteriaEvent::class,
            static function (BlogListingCriteriaEvent $event) use (&$queries): void {
                $query = $event->getRequest()->query->all();
                unset($query['_criteria']);
                $queries[] = $query;
            }
        );

        $url = '/store-api/blog-listing/' . $this->ids->get('category');
        $this->browser->request('GET', $url, ['custom-filter' => '0', 'custom-flag' => '1', 'custom-number' => '5']);

        $compressed = gzencode(json_encode(['custom-filter' => false, 'custom-flag' => true, 'custom-number' => 5, 'custom-empty' => null], \JSON_THROW_ON_ERROR));
        static::assertNotFalse($compressed, 'Gzip compressing failed');
        $this->browser->request('GET', $url, ['_criteria' => Base64::urlEncode($compressed)]);

        static::assertCount(2, $queries);
        static::assertSame(['custom-filter' => '0', 'custom-flag' => '1', 'custom-number' => '5'], $queries[0]);
        static::assertSame($queries[0], $queries[1], 'Listing processors of extensions read the same values as from plain query parameters');
    }

    public function testReturnsHttpNotFoundWhenRequestedPageExceedsLastPage(): void
    {
        $this->createData();

        $this->browser->request(
            'POST',
            '/store-api/blog-listing/' . $this->ids->get('category') . '?p=99'
        );

        $response = $this->browser->getResponse();

        static::assertSame(404, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertArrayHasKey('errors', $payload);
        static::assertSame('BLOG__LISTING_PAGE_OUT_OF_RANGE', $payload['errors'][0]['code']);
    }

    public function testReturnsHttpOkWhenRequestedPageEqualsLastPage(): void
    {
        $this->createData(); // 6 blogs

        // limit=2 → lastPage = ceil(6/2) = 3. p=3 must succeed.
        $this->browser->request(
            'POST',
            '/store-api/blog-listing/' . $this->ids->get('category') . '?p=3&limit=2'
        );

        $response = $this->browser->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('blog_listing', $payload['apiAlias']);
        static::assertSame(3, $payload['page']);
    }

    public function testReturnsHttpOkOnFirstPageEvenWhenOnlyOnePageOfResults(): void
    {
        $this->createData(); // 6 blogs, default limit 24 → lastPage = 1

        $this->browser->request(
            'POST',
            '/store-api/blog-listing/' . $this->ids->get('category') . '?p=1'
        );

        $response = $this->browser->getResponse();
        static::assertSame(200, $response->getStatusCode());

        $payload = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame('blog_listing', $payload['apiAlias']);
        static::assertCount(6, $payload['elements']);
    }

    public function testLoadBlogsUsingDynamicGroupWithEmptyBlogStreamId(): void
    {
        $this->createData('content_stream');

        $this->browser->request(
            'POST',
            '/store-api/blog-listing/' . $this->ids->get('category')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('blog_listing', $response['apiAlias']);
        static::assertCount(6, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
    }

    public function testLoadBlogsUsingDynamicGroupWithBlogStream(): void
    {
        $this->createData('content_stream', $this->ids->create('contentStream'));

        $this->browser->request(
            'POST',
            '/store-api/blog-listing/' . $this->ids->get('category')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('blog_listing', $response['apiAlias']);
        static::assertCount(1, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
        static::assertContains($response['elements'][0]['id'], [$this->variantIds['redL'], $this->variantIds['redXl']]);
    }

    public function testLoadBlogsUsingDynamicGroupWithBlogStreamAndMainVariant(): void
    {
        $this->createData('content_stream', $this->ids->create('contentStream'), 'greenL');

        $this->browser->request(
            'POST',
            '/store-api/blog-listing/' . $this->ids->get('category')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('blog_listing', $response['apiAlias']);
        static::assertCount(1, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
        static::assertSame($this->variantIds['greenL'], $response['elements'][0]['id']);
    }

    public function testLoadBlogsUsingDynamicGroupUpdatesAfterSeparateFilterSync(): void
    {
        $this->createData('content_stream', $this->ids->create('contentStream'));

        $this->browser->request(
            'POST',
            '/store-api/blog-listing/' . $this->ids->get('category')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $response['elements']);
        static::assertContains($response['elements'][0]['id'], [$this->variantIds['redL'], $this->variantIds['redXl']]);

        $filterId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(id)) FROM content_stream_filter WHERE content_stream_id = :streamId',
            ['streamId' => Uuid::fromHexToBytes($this->ids->get('contentStream'))]
        );
        static::assertIsString($filterId);

        $this->contentStreamFilterRepository->update([[
            'id' => $filterId,
            'field' => 'options.id',
            'value' => $this->optionIds['green'],
        ]], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            '/store-api/blog-listing/' . $this->ids->get('category')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertCount(1, $response['elements']);
        static::assertContains($response['elements'][0]['id'], [$this->variantIds['greenL'], $this->variantIds['greenXl']]);
    }

    public function testLoadBlogsUsingDynamicGroupWithOnlyAnEmptyCondition(): void
    {
        $this->createData('content_stream', $this->ids->create('contentStream'));

        $filterId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(id)) FROM content_stream_filter WHERE content_stream_id = :streamId',
            ['streamId' => Uuid::fromHexToBytes($this->ids->get('contentStream'))]
        );
        static::assertIsString($filterId);

        // "Blog" "is equal to any of" without a blog
        $this->contentStreamFilterRepository->update([[
            'id' => $filterId,
            'type' => 'equalsAny',
            'field' => 'id',
            'value' => null,
        ]], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            '/store-api/blog-listing/' . $this->ids->get('category')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('blog_listing', $response['apiAlias']);
        static::assertCount(6, $response['elements']);
    }

    public function testLoadBlogsUsingInvalidDynamicGroup(): void
    {
        $this->createData('content_stream', $this->ids->create('contentStream'));

        $filterId = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(id)) FROM content_stream_filter WHERE content_stream_id = :streamId',
            ['streamId' => Uuid::fromHexToBytes($this->ids->get('contentStream'))]
        );
        static::assertIsString($filterId);

        // A dynamic blog group without any condition is invalid
        $this->contentStreamFilterRepository->delete([['id' => $filterId]], Context::createDefaultContext());

        $this->browser->request(
            'POST',
            '/store-api/blog-listing/' . $this->ids->get('category')
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(200, $this->browser->getResponse()->getStatusCode());
        static::assertSame('blog_listing', $response['apiAlias']);
        static::assertSame(0, $response['total']);
        static::assertCount(0, $response['elements']);
    }

    public function testIncludes(): void
    {
        $this->createData();

        $this->browser->request(
            'POST',
            '/store-api/blog-listing/' . $this->ids->get('category'),
            [
                'includes' => [
                    'blog_listing' => ['total'],
                ],
            ]
        );

        $response = json_decode((string) $this->browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame('blog_listing', $response['apiAlias']);
        static::assertArrayNotHasKey('elements', $response);
        static::assertArrayHasKey('total', $response);
    }

    /**
     * @param array<string, mixed> $blog
     * @param array<string, mixed> $expected
     */
    #[DataProvider('filterAggregationsWithBlogs')]
    public function testFilterAggregationsWithBlogs(IdsCollection $ids, array $blog, Request $request, array $expected): void
    {
        $parent = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL)]
        );

        static::getContainer()->get('category.repository')
            ->create([['id' => $ids->get('category'), 'name' => 'test', 'parentId' => $parent]], Context::createDefaultContext());

        $categoryId = $blog['categories'][0]['id'];

        static::getContainer()->get('blog.repository')
            ->create([$blog], Context::createDefaultContext());

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $listing = static::getContainer()
            ->get(BlogListingRoute::class)
            ->load($categoryId, $request, $context, new Criteria())
            ->getResult();

        $aggregation = $listing->getAggregations()->get($expected['aggregation']);

        if ($expected['instanceOf'] === null) {
            static::assertNull($aggregation);
        } else {
            static::assertInstanceOf($expected['instanceOf'], $aggregation);
        }

        if ($expected['aggregation'] === 'properties' && isset($expected['propertyWhitelistIds'])) {
            static::assertInstanceOf(EntityResult::class, $aggregation);
            /** @var PropertyGroupCollection $properties */
            $properties = $aggregation->getEntities();

            static::assertSame($expected['propertyWhitelistIds'], $properties->getIds());
        }
    }

    /**
     * @return list<array{0: IdsCollection, 1: array<string, mixed>, 2: Request, 3: array<string, mixed>}>
     */
    public static function filterAggregationsWithBlogs(): array
    {
        $ids = new IdsCollection();

        $defaults = [
            'id' => $ids->get('blog'),
            'name' => 'test-blog',
            'blogNumber' => $ids->get('blog'),
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'visibilities' => [
                [
                    'channelId' => TestDefaults::CHANNEL,
                    'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                ],
            ],
            'categories' => [
                ['id' => $ids->get('category')],
            ],
        ];

        return [
            // property-filter
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request(),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => EntityResult::class,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request([], ['property-filter' => true]),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => EntityResult::class,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request([], ['property-filter' => false]),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => null,
                ],
            ],

            // property-whitelist
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request([], ['property-filter' => false, 'property-whitelist' => null]),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => null,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request([], ['property-filter' => false, 'property-whitelist' => null]),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => null,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request([], ['property-filter' => false, 'property-whitelist' => [$ids->get('textile')]]),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => EntityResult::class,
                    'propertyWhitelistIds' => [$ids->get('textile') => $ids->get('textile')],
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'properties' => [
                        [
                            'id' => $ids->get('red'),
                            'name' => 'red',
                            'groupId' => $ids->get('color'),
                            'group' => ['id' => $ids->get('color'), 'name' => 'color'],
                        ],
                        [
                            'id' => $ids->get('cotton'),
                            'name' => 'cotton',
                            'groupId' => $ids->get('textile'),
                            'group' => ['id' => $ids->get('textile'), 'name' => 'textile'],
                        ],
                    ],
                ]),
                new Request([], ['property-filter' => false]),
                [
                    'aggregation' => 'properties',
                    'instanceOf' => null,
                ],
            ],

            // manufacturer-filter
            [
                $ids,
                $defaults,
                new Request(),
                [
                    'aggregation' => 'manufacturer',
                    'instanceOf' => EntityResult::class,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'manufacturer' => [
                        'id' => $ids->get('test-manufacturer'),
                        'name' => 'test-manufacturer',
                    ],
                ]),
                new Request([], ['manufacturer-filter' => true]),
                [
                    'aggregation' => 'manufacturer',
                    'instanceOf' => EntityResult::class,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'manufacturer' => [
                        'id' => $ids->get('test-manufacturer'),
                        'name' => 'test-manufacturer',
                    ],
                ]),
                new Request([], ['manufacturer-filter' => false]),
                [
                    'aggregation' => 'manufacturer',
                    'instanceOf' => null,
                ],
            ],

            // price-filter
            [
                $ids,
                $defaults,
                new Request(),
                [
                    'aggregation' => 'price',
                    'instanceOf' => StatsResult::class,
                ],
            ],
            [
                $ids,
                $defaults,
                new Request([], ['manufacturer-filter' => true]),
                [
                    'aggregation' => 'price',
                    'instanceOf' => StatsResult::class,
                ],
            ],
            [
                $ids,
                $defaults,
                new Request([], ['price-filter' => false]),
                [
                    'aggregation' => 'price',
                    'instanceOf' => null,
                ],
            ],

            // rating-filter
            [
                $ids,
                $defaults,
                new Request(),
                [
                    'aggregation' => 'rating',
                    'instanceOf' => MaxResult::class,
                ],
            ],
            [
                $ids,
                $defaults,
                new Request([], ['rating-filter' => true]),
                [
                    'aggregation' => 'rating',
                    'instanceOf' => MaxResult::class,
                ],
            ],
            [
                $ids,
                $defaults,
                new Request([], ['rating-filter' => false]),
                [
                    'aggregation' => 'rating',
                    'instanceOf' => null,
                ],
            ],

            // shipping-free-filter
            [
                $ids,
                array_merge($defaults, [
                    'shippingFree' => false,
                ]),
                new Request(),
                [
                    'aggregation' => 'shipping-free',
                    'instanceOf' => MaxResult::class,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'shippingFree' => true,
                ]),
                new Request([], ['shipping-free-filter' => true]),
                [
                    'aggregation' => 'shipping-free',
                    'instanceOf' => MaxResult::class,
                ],
            ],
            [
                $ids,
                array_merge($defaults, [
                    'shippingFree' => true,
                ]),
                new Request([], ['shipping-free-filter' => false]),
                [
                    'aggregation' => 'shipping-free',
                    'instanceOf' => null,
                ],
            ],
        ];
    }

    private function createData(string $blogAssignmentType = 'blog', ?string $contentStreamId = null, ?string $mainVariant = null): void
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

        $this->groupIds = [
            'color' => Uuid::randomHex(),
            'size' => Uuid::randomHex(),
        ];

        $blog = [
            'name' => 'test',
            'stock' => 10,
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false],
            ],
            'tax' => ['name' => 'test', 'taxRate' => 15],
            'active' => true,
        ];

        $blogs = [];
        for ($i = 0; $i < 5; ++$i) {
            $blogs[] = array_merge(
                [
                    'id' => $this->ids->create('blog' . $i),
                    'manufacturer' => ['id' => $this->ids->create('manufacturer-' . $i), 'name' => 'test-' . $i],
                    'blogNumber' => $this->ids->get('blog' . $i),
                ],
                $blog
            );
        }

        $blog['id'] = $this->blogId;
        $blog['configuratorSettings'] = [
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
        ];
        $blog['children'] = [
            [
                'id' => $this->variantIds['redXl'],
                'blogNumber' => 'a.1',
                'stock' => 10,
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
                'active' => true,
                'parentId' => $this->blogId,
                'options' => [
                    ['id' => $this->optionIds['green']],
                    ['id' => $this->optionIds['l']],
                ],
            ],
        ];
        $blog['blogNumber'] = $this->blogId;
        $blogs[] = $blog;

        $data = [
            'id' => $this->ids->create('category'),
            'name' => 'Test',
            'blogAssignmentType' => $blogAssignmentType,
            'cmsPage' => [
                'id' => $this->ids->create('cms-page'),
                'type' => 'blog_list',
                'sections' => [
                    [
                        'position' => 0,
                        'type' => 'sidebar',
                        'blocks' => [
                            [
                                'type' => 'blog-listing',
                                'position' => 1,
                                'slots' => [
                                    ['type' => 'blog-listing', 'slot' => 'content'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'blogs' => $blogs,
        ];

        static::getContainer()->get('content_stream.repository')->create([[
            'id' => $this->ids->create('contentStream'),
            'name' => 'test',
            'filters' => [[
                'type' => 'equals',
                'field' => 'options.id',
                'value' => $this->optionIds['red'],
            ]],
        ]], Context::createDefaultContext());

        $this->categoryRepository->upsert([$data], Context::createDefaultContext());
        $this->categoryRepository->upsert([[
            'id' => $this->ids->get('category'),
            'contentStreamId' => $contentStreamId,
        ]], Context::createDefaultContext());

        if ($mainVariant) {
            $upsertData = [
                [
                    'id' => $this->blogId,
                    'variantListingConfig' => [
                        'mainVariantId' => $this->variantIds['greenL'],
                    ],
                ],
            ];
            $this->blogRepository->upsert($upsertData, Context::createDefaultContext());
        }

        $this->browser = $this->createCustomChannelBrowser([
            'id' => $this->ids->get('sales-channel'),
            'navigationCategoryId' => $this->ids->get('category'),
        ]);

        $this->setVisibilities($blogs);
    }

    /**
     * @param array<int, array<string, array<int|string, array<string, array<int|string, array<string, string>|string>|bool|int|string>|int|string>|bool|int|string>> $createdBlogs
     */
    private function setVisibilities(array $createdBlogs): void
    {
        $blogs = [];
        foreach ($createdBlogs as $created) {
            $blogs[] = [
                'id' => $created['id'],
                'visibilities' => [
                    ['channelId' => $this->ids->get('sales-channel'), 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        $this->blogRepository->update($blogs, Context::createDefaultContext());
    }
}
