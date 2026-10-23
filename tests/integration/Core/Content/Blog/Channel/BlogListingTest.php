<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel;

use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Content\Test\Blog\Channel\Fixture\ListingTestData;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Test\TestCaseBase\ChannelFunctionalTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BlogListingTest extends TestCase
{
    use ChannelFunctionalTestBehaviour;

    private string $categoryId;

    private ListingTestData $testData;

    private string $categoryStreamId;

    private Context $context;

    private string $blogIdWidth100;

    private string $blogIdWidth150;

    private string $channelId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = Context::createDefaultContext();

        $channel = $this->createChannel();
        $this->channelId = $channel['id'];

        $parent = $channel['navigationCategoryId'];

        $this->categoryId = Uuid::randomHex();

        static::getContainer()->get('category.repository')
            ->create([['id' => $this->categoryId, 'name' => 'test', 'parentId' => $parent]], Context::createDefaultContext());

        $this->testData = new ListingTestData();

        $this->insertOptions();

        $this->insertBlogs();

        $this->categoryStreamId = Uuid::randomHex();
    }

    public function testListing(): void
    {
        $request = new Request();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->channelId);

        $listing = static::getContainer()
            ->get(BlogListingRoute::class)
            ->load($this->categoryId, $request, $context, new Criteria())
            ->getResult();
        $blogs = $listing->getEntities();

        static::assertCount(10, $blogs);
        static::assertFalse($blogs->has($this->testData->getId('blog1')));

        self::assertVariationsInListing($blogs, [
            $this->testData->getId('blog1-red-l-steel'),
            $this->testData->getId('blog1-red-xl-steel'),
            $this->testData->getId('blog1-red-l-iron'),
            $this->testData->getId('blog1-red-xl-iron'),
        ]);
        self::assertVariationsInListing($blogs, [
            $this->testData->getId('blog1-green-l-steel'),
            $this->testData->getId('blog1-green-xl-steel'),
            $this->testData->getId('blog1-green-l-iron'),
            $this->testData->getId('blog1-green-xl-iron'),
        ]);

        // blog 2 should display only the both color variants
        static::assertFalse($blogs->has($this->testData->getId('blog2')));
        static::assertTrue($blogs->has($this->testData->getId('blog2-green')));
        static::assertTrue($blogs->has($this->testData->getId('blog2-red')));

        // blog 3 has no variants
        static::assertTrue($blogs->has($this->testData->getId('blog3')));

        self::assertVariationsInListing($blogs, [
            $this->testData->getId('blog4-red-l-iron'),
            $this->testData->getId('blog4-red-xl-iron'),
        ]);
        self::assertVariationsInListing($blogs, [
            $this->testData->getId('blog4-red-l-steel'),
            $this->testData->getId('blog4-red-xl-steel'),
        ]);
        self::assertVariationsInListing($blogs, [
            $this->testData->getId('blog4-green-l-iron'),
            $this->testData->getId('blog4-green-xl-iron'),
        ]);
        self::assertVariationsInListing($blogs, [
            $this->testData->getId('blog4-green-l-steel'),
            $this->testData->getId('blog4-green-xl-steel'),
        ]);

        self::assertVariationsInListing($blogs, [
            $this->testData->getId('blog5-red'),
            $this->testData->getId('blog5-green'),
        ]);

        $result = $listing->getAggregations()->get('properties');
        static::assertInstanceOf(EntityResult::class, $result);

        $options = $result->getEntities();
        static::assertInstanceOf(PropertyGroupCollection::class, $options);
        $ids = array_keys($options->getOptionIdMap());

        static::assertContains($this->testData->getId('green'), $ids);
        static::assertContains($this->testData->getId('red'), $ids);
        static::assertContains($this->testData->getId('xl'), $ids);
        static::assertContains($this->testData->getId('l'), $ids);
        static::assertContains($this->testData->getId('iron'), $ids);
        static::assertContains($this->testData->getId('steel'), $ids);
        static::assertFalse($options->has($this->testData->getId('yellow')));
        static::assertFalse($options->has($this->testData->getId('cotton')));
    }

    public function testListingWithBlogStream(): void
    {
        $this->createTestBlogStreamEntity($this->categoryStreamId);
        $request = new Request();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->channelId);

        $listing = static::getContainer()
            ->get(BlogListingRoute::class)
            ->load($this->categoryStreamId, $request, $context, new Criteria())
            ->getResult();

        static::assertSame(7, $listing->getTotal());
        static::assertFalse($listing->getEntities()->has($this->blogIdWidth100));
        static::assertTrue($listing->getEntities()->has($this->blogIdWidth150));
    }

    public function testListingWithBlogStreamAndAdditionalCriteria(): void
    {
        $this->createTestBlogStreamEntity($this->categoryStreamId);
        $request = new Request();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->channelId);

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('name', 'Foo Bar'));

        $listing = static::getContainer()
            ->get(BlogListingRoute::class)
            ->load($this->categoryStreamId, $request, $context, $criteria)
            ->getResult();

        static::assertSame(3, $listing->getTotal());
        $firstFilter = $listing->getCriteria()->getFilters()[0];
        static::assertInstanceOf(ContainsFilter::class, $firstFilter);
        static::assertSame('name', $firstFilter->getField());
        static::assertSame('Foo Bar', $firstFilter->getValue());
    }

    public function testNotFilterableProperty(): void
    {
        $request = new Request();

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), $this->channelId);

        $request->attributes->set('_route_params', [
            'navigationId' => $this->categoryId,
        ]);

        $listing = static::getContainer()
            ->get(BlogListingRoute::class)
            ->load($this->categoryId, $request, $context, new Criteria())
            ->getResult();

        /** @var EntityResult<PropertyGroupCollection> $result */
        $result = $listing->getAggregations()->get('properties');
        $propertyGroups = $result->getEntities();

        $propertyGroupIds = [];

        foreach ($propertyGroups as $propertyGroup) {
            $propertyGroupIds[] = $propertyGroup->getId();
        }

        static::assertContains($this->testData->getId('color'), $propertyGroupIds);
        static::assertContains($this->testData->getId('size'), $propertyGroupIds);
        static::assertContains($this->testData->getId('material'), $propertyGroupIds);
        static::assertNotContains($this->testData->getId('class'), $propertyGroupIds);
    }

    /**
     * Small helper function which asserts the one of the provided pool ids are in the result set but the remaining ids are excluded.
     *
     * @param array<string> $pool
     */
    private static function assertVariationsInListing(BlogCollection $result, array $pool): void
    {
        $match = null;
        // find matching id
        foreach ($pool as $index => $id) {
            if ($result->has($id)) {
                $match = $id;
                unset($pool[$index]);

                break;
            }
        }
        // assert that one id found
        static::assertNotNull($match);

        // after one id found, assert that all other ids are not inside the result set
        foreach ($pool as $id) {
            static::assertFalse($result->has($id));
        }
    }

    private function insertBlogs(): void
    {
        $this->createBlog(
            'blog1',
            [
                [$this->testData->getId('red'), $this->testData->getId('green')],
                [$this->testData->getId('xl'), $this->testData->getId('l')],
                [$this->testData->getId('iron'), $this->testData->getId('steel')],
            ],
            [$this->testData->getId('color')]
        );

        $this->createBlog(
            'blog2',
            [
                [$this->testData->getId('red'), $this->testData->getId('green')],
            ],
            [$this->testData->getId('color')]
        );

        $this->createBlog('blog3', [], []);

        $this->createBlog(
            'blog4',
            [
                [$this->testData->getId('red'), $this->testData->getId('green')],
                [$this->testData->getId('xl'), $this->testData->getId('l')],
                [$this->testData->getId('iron'), $this->testData->getId('steel')],
            ],
            [$this->testData->getId('color'), $this->testData->getId('material')]
        );

        $this->createBlog(
            'blog5',
            [
                [$this->testData->getId('red'), $this->testData->getId('green')],
            ],
            []
        );
    }

    /**
     * @param array<array<string>> $options
     * @param array<string> $listingGroups
     */
    private function createBlog(string $key, array $options, array $listingGroups): void
    {
        $config = [];
        foreach ($listingGroups as $groupId) {
            $config[] = [
                'id' => $groupId,
                'expressionForListings' => true,
                'representation' => 'box', // box, select, image, color
            ];
        }

        $configurator = [];
        foreach ($options as $grouped) {
            foreach ($grouped as $optionId) {
                $configurator[] = ['optionId' => $optionId];
            }
        }

        $id = $this->testData->createId($key);
        $data = [
            [
                'id' => $id,
                'variantListingConfig' => [
                    'configuratorGroupConfig' => $config,
                ],
                'blogNumber' => $id,
                'manufacturer' => ['name' => 'test'],
                'tax' => ['taxRate' => 19, 'name' => 'test'],
                'stock' => 10,
                'name' => $key,
                'active' => true,
                'type' => BlogDefinition::TYPE_PHYSICAL,
                'price' => [
                    ['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => true],
                ],
                'categories' => [
                    ['id' => $this->categoryId],
                ],
                'configuratorSettings' => $configurator,
                'visibilities' => [
                    [
                        'channelId' => $this->channelId,
                        'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
        ];

        if ($options !== []) {
            foreach ($this->combos($options) as $index => $combination) {
                $variantKey = $key . '-' . implode('-', $this->testData->getKeyList($combination));

                $data[] = [
                    'id' => $this->testData->createId($variantKey),
                    'blogNumber' => $key . '.' . $index,
                    'stock' => 10,
                    'name' => $variantKey,
                    'active' => true,
                    'parentId' => $this->testData->getId($key),
                    'options' => array_map(static fn ($id) => ['id' => $id], $combination),
                ];
            }
        }

        $repo = static::getContainer()->get('blog.repository');

        $repo->create($data, Context::createDefaultContext());
    }

    /**
     * Rec. Function to find all possible combinations of $data input
     *
     * @param array<array<string>> $data
     * @param array<array<string>> $all
     * @param array<string> $group
     *
     * @return array<array<string>>
     */
    private function combos(array $data, &$all = [], $group = [], ?string $val = null, int $i = 0): array
    {
        if (isset($val)) {
            $group[] = $val;
        }
        if ($i >= \count($data)) {
            $all[] = $group;
        } else {
            foreach ($data[$i] as $v) {
                $this->combos($data, $all, $group, $v, $i + 1);
            }
        }

        return $all;
    }

    private function insertOptions(): void
    {
        $data = [
            [
                'id' => $this->testData->createId('color'),
                'name' => 'color',
                'options' => [
                    ['id' => $this->testData->createId('green'), 'name' => 'green'],
                    ['id' => $this->testData->createId('red'), 'name' => 'red'],
                    ['id' => $this->testData->createId('yellow'), 'name' => 'red'],
                ],
            ],
            [
                'id' => $this->testData->createId('size'),
                'name' => 'size',
                'options' => [
                    ['id' => $this->testData->createId('xl'), 'name' => 'XL'],
                    ['id' => $this->testData->createId('l'), 'name' => 'L'],
                ],
            ],
            [
                'id' => $this->testData->createId('material'),
                'name' => 'material',
                'options' => [
                    ['id' => $this->testData->createId('iron'), 'name' => 'iron'],
                    ['id' => $this->testData->createId('steel'), 'name' => 'steel'],
                    ['id' => $this->testData->createId('cotton'), 'name' => 'steel'],
                ],
            ],
            [
                'id' => $this->testData->createId('class'),
                'name' => 'class',
                'options' => [
                    ['id' => $this->testData->createId('first'), 'name' => 'first'],
                    ['id' => $this->testData->createId('business'), 'name' => 'business'],
                    ['id' => $this->testData->createId('coach'), 'name' => 'coach'],
                ],
            ],
        ];

        static::getContainer()->get('property_group.repository')->create($data, Context::createDefaultContext());
    }

    private function createTestBlogStreamEntity(string $categoryStreamId): void
    {
        $streamId = Uuid::randomHex();

        $randomBlogIds = implode('|', array_column($this->createBlogs(), 'id'));

        $stream = [
            'id' => $streamId,
            'name' => 'testStream',
            'filters' => [
                [
                    'type' => 'multi',
                    'queries' => [
                        [
                            'type' => 'equalsAny',
                            'field' => 'blog.id',
                            'value' => $randomBlogIds,
                        ],
                        [
                            'type' => 'range',
                            'field' => 'blog.width',
                            'parameters' => [
                                'gte' => 120,
                                'lte' => 180,
                            ],
                        ],
                    ],
                    'operator' => 'AND',
                ],
            ],
        ];
        $blogRepository = static::getContainer()->get('content_stream.repository');
        $blogRepository->create([$stream], $this->context);

        static::getContainer()->get('category.repository')
            ->create([['id' => $categoryStreamId, 'contentStreamId' => $streamId, 'name' => 'test', 'parentId' => null, 'blogAssignmentType' => 'content_stream']], Context::createDefaultContext());
    }

    /**
     * @return array<array{id: string, blogNumber: string, width: string, stock: int, name: string, price: array<array{currencyId: string, gross: int, net: int, linked: bool}>, manufacturer: array{id: string, name: string}, tax: array{id: string, taxRate: int, name: string}, visibilities: array<array{channelId: string, visibility: int}>}>
     */
    private function createBlogs(): array
    {
        $ids = new IdsCollection();
        $ids->create('manufacturer');
        $ids->create('taxId');

        $blogRepository = static::getContainer()->get('blog.repository');
        $channelId = $this->channelId;
        $blogs = [];

        $widths = [
            '100',
            '110',
            '120',
            '130',
            '140',
            '150',
            '160',
            '170',
            '180',
            '190',
        ];

        $names = [
            'Wooden Heavy Magma',
            'Small Plastic Prawn Leather',
            'Fantastic Marble Megahurts',
            'Foo Bar Aerodynamic Iron Viagreat',
            'Foo Bar Awesome Bronze Sulpha Quik',
            'Foo Bar Aerodynamic Silk Ideoswitch',
            'Heavy Duty Wooden Magnina',
            'Incredible Wool Q-lean',
            'Heavy Duty Cotton Gristle Chips',
            'Heavy Steel Hot Magma',
        ];

        for ($i = 0; $i < 10; ++$i) {
            $blogs[] = [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'width' => $widths[$i],
                'stock' => 1,
                'name' => $names[$i],
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $ids->get('manufacturer'), 'name' => 'test'],
                'tax' => ['id' => $ids->get('taxId'), 'taxRate' => 17, 'name' => 'with id'],
                'visibilities' => [
                    ['channelId' => $channelId, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
            ];
        }

        $this->blogIdWidth100 = $blogs[0]['id'];
        $this->blogIdWidth150 = $blogs[5]['id'];

        $blogRepository->create($blogs, $this->context);

        return $blogs;
    }
}
