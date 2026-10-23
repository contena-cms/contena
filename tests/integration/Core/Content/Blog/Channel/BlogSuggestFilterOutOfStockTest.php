<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingRoute;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Content\Test\Blog\Channel\Fixture\ListingTestData;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BlogSuggestFilterOutOfStockTest extends TestCase
{
    use IntegrationTestBehaviour;

    private string $categoryId;

    private ListingTestData $testData;

    protected function setUp(): void
    {
        parent::setUp();

        $parent = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT LOWER(HEX(navigation_category_id)) FROM channel WHERE id = :id',
            ['id' => Uuid::fromHexToBytes(TestDefaults::CHANNEL)]
        );

        $this->categoryId = Uuid::randomHex();

        static::getContainer()->get('category.repository')
            ->create([['id' => $this->categoryId, 'name' => 'test', 'parentId' => $parent]], Context::createDefaultContext());

        $this->testData = new ListingTestData();

        $this->insertOptions();

        $this->insertBlogs();
    }

    public function testListingWithFilterDisabled(): void
    {
        // disable hideCloseoutBlogsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', false);

        $request = new Request();

        $request->query->set('search', ['blog']);

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $listing = static::getContainer()
            ->get(BlogListingRoute::class)
            ->load($this->categoryId, $request, $context, new Criteria())
            ->getResult();

        static::assertSame(5, $listing->getTotal());
        static::assertFalse($listing->getEntities()->has($this->testData->getId('blog1')));
        static::assertFalse($listing->getEntities()->has($this->testData->getId('blog2')));

        // blog 1 has all available variants
        static::assertTrue($listing->getEntities()->has($this->testData->getId('blog1-red')));
        static::assertTrue($listing->getEntities()->has($this->testData->getId('blog1-green')));
        static::assertTrue($listing->getEntities()->has($this->testData->getId('blog1-blue')));

        // blog 2 has all available variants
        static::assertTrue($listing->getEntities()->has($this->testData->getId('blog2-green')));
        static::assertTrue($listing->getEntities()->has($this->testData->getId('blog2-red')));

        /** @var EntityResult<PropertyGroupCollection> $result */
        $result = $listing->getAggregations()->get('properties');
        $options = $result->getEntities();

        $ids = array_keys($options->getOptionIdMap());

        static::assertContains($this->testData->getId('green'), $ids);
        static::assertContains($this->testData->getId('red'), $ids);
        static::assertContains($this->testData->getId('blue'), $ids);
    }

    public function testListingWithFilterEnabled(): void
    {
        // enable hideCloseoutBlogsWhenOutOfStock filter
        static::getContainer()->get(SystemConfigService::class)
            ->set('core.listing.hideCloseoutBlogsWhenOutOfStock', true);

        $request = new Request();

        $request->query->set('search', ['blog']);

        $context = static::getContainer()->get(ChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::CHANNEL);

        $listing = static::getContainer()
            ->get(BlogListingRoute::class)
            ->load($this->categoryId, $request, $context, new Criteria())
            ->getResult();

        static::assertSame(2, $listing->getTotal());
        static::assertFalse($listing->getEntities()->has($this->testData->getId('blog1')));
        static::assertFalse($listing->getEntities()->has($this->testData->getId('blog2')));

        // blog 1 has only 2 available variants
        static::assertTrue($listing->getEntities()->has($this->testData->getId('blog1-red')));
        static::assertTrue($listing->getEntities()->has($this->testData->getId('blog1-green')));
        static::assertFalse($listing->getEntities()->has($this->testData->getId('blog1-blue')));

        // blog 2 has no available variants
        static::assertFalse($listing->getEntities()->has($this->testData->getId('blog2-green')));
        static::assertFalse($listing->getEntities()->has($this->testData->getId('blog2-red')));

        /** @var EntityResult<PropertyGroupCollection> $result */
        $result = $listing->getAggregations()->get('properties');
        $options = $result->getEntities();

        $ids = array_keys($options->getOptionIdMap());

        static::assertContains($this->testData->getId('green'), $ids);
        static::assertContains($this->testData->getId('red'), $ids);
        static::assertNotContains($this->testData->getId('blue'), $ids);
    }

    private function insertBlogs(): void
    {
        $this->createBlog(
            'blog1',
            [
                [
                    'combination' => [$this->testData->getId('red')],
                    'stock' => 1,
                ],
                [
                    'combination' => [$this->testData->getId('blue')],
                    'stock' => 0,
                ],
                [
                    'combination' => [$this->testData->getId('green')],
                    'stock' => 1,
                ],
            ],
            [$this->testData->getId('color')]
        );

        $this->createBlog(
            'blog2',
            [
                [
                    'combination' => [$this->testData->getId('red')],
                    'stock' => 0,
                ],
                [
                    'combination' => [$this->testData->getId('green')],
                    'stock' => 0,
                ],
            ],
            [$this->testData->getId('color')]
        );
    }

    /**
     * @param array<int, array{combination:array<mixed>, stock: int}> $options
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
            foreach ($grouped['combination'] as $optionId) {
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
                'stock' => 0,
                'isCloseout' => true,
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
                        'channelId' => TestDefaults::CHANNEL,
                        'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL,
                    ],
                ],
            ],
        ];

        if ($options !== []) {
            foreach ($options as $index => $option) {
                // $combination = $this->combos($option['combination']);
                $combination = $option['combination'];

                $variantKey = $key . '-' . implode('-', $this->testData->getKeyList($combination));

                $data[] = [
                    'id' => $this->testData->createId($variantKey),
                    'blogNumber' => $key . '.' . $index,
                    'stock' => $option['stock'],
                    'isCloseout' => true,
                    'name' => $variantKey,
                    'active' => true,
                    'parentId' => $this->testData->getId($key),
                    'options' => array_map(static fn ($id) => ['id' => $id], $combination),
                ];
            }
        }

        static::getContainer()->get('blog.repository')->create($data, Context::createDefaultContext());
    }

    private function insertOptions(): void
    {
        static::getContainer()->get('property_group.repository')->create([
            [
                'id' => $this->testData->createId('color'),
                'name' => 'color',
                'options' => [
                    ['id' => $this->testData->createId('green'), 'name' => 'green'],
                    ['id' => $this->testData->createId('red'), 'name' => 'red'],
                    ['id' => $this->testData->createId('blue'), 'name' => 'blue'],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
