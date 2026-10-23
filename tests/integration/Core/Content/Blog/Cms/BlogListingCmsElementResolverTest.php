<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Cms;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Sorting\BlogSortingEntity;
use Contena\Core\Content\Blog\Cms\BlogListingCmsElementResolver;
use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Content\Cms\Channel\Struct\BlogListingStruct;
use Contena\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Contena\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class BlogListingCmsElementResolverTest extends TestCase
{
    use IntegrationTestBehaviour;

    private BlogListingCmsElementResolver $blogListingCMSElementResolver;

    private ChannelContext $channelContext;

    private Connection $connection;

    /**
     * @var array<string|int, mixed>
     */
    private array $blogSortings;

    protected function setUp(): void
    {
        parent::setUp();
        $this->blogListingCMSElementResolver = static::getContainer()->get(BlogListingCmsElementResolver::class);
        $this->channelContext = $this->createChannelContext();

        $this->connection = KernelLifecycleManager::getConnection();

        $this->blogSortings = $this->getBlogSortings();
    }

    public function testSortings(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    $this->blogSortings['price-desc'] => 1,
                    $this->blogSortings['name-asc'] => 0,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $availableSortings = $slotConfig['availableSortings']['value'];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->channelContext,
            new Request([], ['order' => 'name-asc'])
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->blogListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(BlogListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(BlogListingResult::class, $listing);

        static::assertSame('name-asc', $listing->getSorting());

        foreach ($listing->getAvailableSortings() as $availableSorting) {
            static::assertArrayHasKey($availableSorting->getId(), $availableSortings);
        }
    }

    public function testDefaultSorting(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    $this->blogSortings['price-desc'] => 1,
                    $this->blogSortings['name-asc'] => 0,
                ],
            ],
            'defaultSorting' => ['value' => $this->blogSortings['name-asc']],
            'useCustomSorting' => ['value' => true],
        ];

        $availableSortings = $slotConfig['availableSortings']['value'];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->channelContext,
            new Request()
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->blogListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(BlogListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(BlogListingResult::class, $listing);

        static::assertSame('name-asc', $listing->getSorting());

        foreach ($listing->getAvailableSortings() as $availableSorting) {
            static::assertArrayHasKey($availableSorting->getId(), $availableSortings);
        }
    }

    public function testUnavailableSortingThrowsNoException(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    $this->blogSortings['price-desc'] => 1,
                    $this->blogSortings['name-asc'] => 0,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->channelContext,
            new Request([], ['order' => 'unavailable-order'])
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->blogListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        static::assertEquals(new ElementDataCollection(), $result);
    }

    public function testOnlyRestrictedSortingsAreAvailable(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    $this->blogSortings['price-desc'] => 1,
                    $this->blogSortings['price-asc'] => 0,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $availableSortings = $slotConfig['availableSortings']['value'];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->channelContext,
            new Request([], ['order' => 'price-desc'])
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->blogListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(BlogListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(BlogListingResult::class, $listing);

        $actualSortings = $listing->getAvailableSortings()->map(static fn (BlogSortingEntity $actualSorting) => $actualSorting->getId());

        $availableSortings = array_keys($availableSortings);

        sort($actualSortings);
        sort($availableSortings);

        static::assertSame($availableSortings, $actualSortings);
    }

    public function testAvailableSortingsPriority(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    $this->blogSortings['price-desc'] => 1,
                    $this->blogSortings['price-asc'] => 100,
                    $this->blogSortings['name-asc'] => 77,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $availableSortings = $slotConfig['availableSortings']['value'];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->channelContext,
            new Request([], ['order' => 'price-desc'])
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->blogListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(BlogListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(BlogListingResult::class, $listing);

        $actualSortings = $listing->getAvailableSortings()->map(static fn (BlogSortingEntity $actualSorting) => $actualSorting->getId());

        $actualSortings = array_values($actualSortings);

        arsort($availableSortings);
        $availableSortings = array_keys($availableSortings);

        static::assertSame($availableSortings, $actualSortings);
    }

    public function testHighestPrioritySortingIsDefaultSorting(): void
    {
        $slotConfig = [
            'availableSortings' => [
                'value' => [
                    $this->blogSortings['price-desc'] => 1,
                    $this->blogSortings['price-asc'] => 100,
                    $this->blogSortings['name-asc'] => 77,
                ],
            ],
            'useCustomSorting' => ['value' => true],
        ];

        $result = new ElementDataCollection();

        $resolverContext = new ResolverContext(
            $this->channelContext,
            new Request()
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-listing');
        $slot->addTranslated('config', $slotConfig);

        $this->blogListingCMSElementResolver->enrich($slot, $resolverContext, $result);

        $data = $slot->getData();
        static::assertInstanceOf(BlogListingStruct::class, $data);

        $listing = $data->getListing();
        static::assertInstanceOf(BlogListingResult::class, $listing);

        $sorting = $listing->getSorting();

        static::assertSame('price-asc', $sorting);
    }

    /**
     * @param array<string, mixed> $expectations
     * @param array<string, mixed> $slotConfig
     */
    #[DataProvider('filtersProvider')]
    public function testFiltersAndPropertyWhitelist(array $expectations, array $slotConfig): void
    {
        $resolverContext = new ResolverContext(
            $this->channelContext,
            new Request()
        );

        $slot = new CmsSlotEntity();
        $slot->setUniqueIdentifier('id');
        $slot->setType('blog-listing');
        $slot->setConfig($slotConfig);
        $slot->addTranslated('config', $slotConfig);

        $this->blogListingCMSElementResolver->enrich($slot, $resolverContext, new ElementDataCollection());

        $request = $resolverContext->getRequest();

        foreach ($expectations as $field => $expected) {
            if ($field === 'property-whitelist') {
                $value = $request->request->all($field);
            } else {
                $value = $request->request->get($field, true);
            }

            static::assertSame($expected, $value);
        }
    }

    /**
     * @return iterable<list<array<string, mixed>>>
     */
    public static function filtersProvider(): iterable
    {
        $sizeId = Uuid::randomHex();
        $textileId = Uuid::randomHex();

        yield 'default config keeps all filters enabled without a property whitelist' => [
            [
                'manufacturer-filter' => true,
                'price-filter' => true,
                'rating-filter' => true,
                'shipping-free-filter' => true,
                'property-filter' => true,
                'property-whitelist' => [],
            ],
            [
                'filters' => [
                    'value' => null,
                ],
                'propertyWhitelist' => null,
            ],
        ];
        yield 'invalid filter value is preserved when no known filters are active' => [
            [
                'manufacturer-filter' => false,
                'price-filter' => false,
                'rating-filter' => false,
                'shipping-free-filter' => false,
                'property-filter' => false,
                'property-whitelist' => [],
            ],
            [
                'filters' => [
                    'value' => 'invalid-filter',
                ],
                'propertyWhitelist' => null,
            ],
        ];
        yield 'invalid filter value is preserved before active manufacturer and rating filters' => [
            [
                'manufacturer-filter' => true,
                'price-filter' => false,
                'rating-filter' => true,
                'shipping-free-filter' => false,
                'property-filter' => false,
                'property-whitelist' => [],
            ],
            [
                'filters' => [
                    'value' => 'invalid-filter,manufacturer-filter,rating-filter',
                ],
                'propertyWhitelist' => null,
            ],
        ];
        yield 'all active filters are returned when every filter is enabled' => [
            [
                'manufacturer-filter' => true,
                'price-filter' => true,
                'rating-filter' => true,
                'shipping-free-filter' => true,
                'property-filter' => true,
                'property-whitelist' => [],
            ],
            [
                'filters' => [
                    'value' => 'manufacturer-filter,price-filter,rating-filter,property-filter,shipping-free-filter',
                ],
                'propertyWhitelist' => ['value' => []],
            ],
        ];
        yield 'disabled manufacturer filter is omitted from active filters' => [
            [
                'manufacturer-filter' => false,
                'price-filter' => true,
                'rating-filter' => true,
                'shipping-free-filter' => true,
                'property-filter' => true,
                'property-whitelist' => [],
            ],
            [
                'filters' => [
                    'value' => 'price-filter,rating-filter,property-filter,shipping-free-filter',
                ],
                'propertyWhitelist' => ['value' => []],
            ],
        ];
        yield 'disabled manufacturer and price filters are omitted from active filters' => [
            [
                'manufacturer-filter' => false,
                'price-filter' => false,
                'rating-filter' => true,
                'shipping-free-filter' => true,
                'property-filter' => true,
                'property-whitelist' => [],
            ],
            [
                'filters' => [
                    'value' => 'rating-filter,property-filter,shipping-free-filter',
                ],
                'propertyWhitelist' => ['value' => []],
            ],
        ];
        yield 'property and shipping filters are enabled when both are active' => [
            [
                'manufacturer-filter' => false,
                'price-filter' => false,
                'rating-filter' => false,
                'shipping-free-filter' => true,
                'property-filter' => true,
                'property-whitelist' => [],
            ],
            [
                'filters' => [
                    'value' => 'property-filter,shipping-free-filter',
                ],
                'propertyWhitelist' => ['value' => []],
            ],
        ];
        yield 'only property filter is enabled when shipping filter is inactive' => [
            [
                'manufacturer-filter' => false,
                'price-filter' => false,
                'rating-filter' => false,
                'shipping-free-filter' => false,
                'property-filter' => true,
                'property-whitelist' => [],
            ],
            [
                'filters' => [
                    'value' => 'property-filter',
                ],
                'propertyWhitelist' => ['value' => []],
            ],
        ];
        yield 'all filters are disabled and property whitelist is empty' => [
            [
                'manufacturer-filter' => false,
                'price-filter' => false,
                'rating-filter' => false,
                'shipping-free-filter' => false,
                'property-filter' => false,
                'property-whitelist' => [],
            ],
            [
                'filters' => [
                    'value' => '',
                ],
                'propertyWhitelist' => ['value' => []],
            ],
        ];
        yield 'disabled filters keep the configured property whitelist' => [
            [
                'manufacturer-filter' => false,
                'price-filter' => false,
                'rating-filter' => false,
                'shipping-free-filter' => false,
                'property-filter' => false,
                'property-whitelist' => [$sizeId, $textileId],
            ],
            [
                'filters' => [
                    'value' => '',
                ],
                'propertyWhitelist' => ['value' => [$sizeId, $textileId]],
            ],
        ];
        yield 'property filter keeps the configured property whitelist' => [
            [
                'manufacturer-filter' => false,
                'price-filter' => false,
                'rating-filter' => false,
                'shipping-free-filter' => false,
                'property-filter' => true,
                'property-whitelist' => [],
            ],
            [
                'filters' => [
                    'value' => 'property-filter',
                ],
                'propertyWhitelist' => ['value' => [$sizeId, $textileId]],
            ],
        ];
    }

    private function createChannelContext(): ChannelContext
    {
        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);

        return $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    /**
     * @return array<string|int, mixed>
     */
    private function getBlogSortings(): array
    {
        $sortings = $this->connection->fetchAllKeyValue('SELECT url_key, id FROM blog_sorting;');

        $sortings = array_map(static fn ($value) => Uuid::fromBytesToHex($value), $sortings);

        return $sortings;
    }
}
