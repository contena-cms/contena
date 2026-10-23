<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\Blog\Channel;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogSearchConfig\BlogSearchConfigCollection;
use Contena\Core\Content\Blog\DataAbstractionLayer\SearchKeywordUpdater;
use Contena\Core\Content\Blog\Events\BlogSearchCriteriaEvent;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogEvents;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Search\BlogSearchRoute;
use Contena\Core\Content\Blog\Channel\Suggest\BlogSuggestRoute;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchKeywordAnalyzer;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\Annotation\CriteriaValueResolver;
use Contena\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Util\Base64;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Migration\V6_7\Migration1775460999AddParentNameToBlogSearchConfig;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;

/**
 * @internal
 */
#[Group('store-api')]
class BlogSearchRouteTest extends TestCase
{
    use IntegrationTestBehaviour;
    use ChannelApiTestBehaviour;

    private static KernelBrowser $browser;

    private static IdsCollection $ids;

    private static bool $initialized = false;

    private string $blogSearchConfigId;

    /**
     * @var EntityRepository<BlogSearchConfigCollection>
     */
    private EntityRepository $blogSearchConfigRepository;

    private SearchKeywordUpdater $searchKeywordUpdater;

    /**
     * @var array<string, string>|null
     */
    private ?array $parentNameSearchState = null;

    protected function setUp(): void
    {
        $this->searchKeywordUpdater = static::getContainer()->get(SearchKeywordUpdater::class);
        $this->blogSearchConfigRepository = static::getContainer()->get('blog_search_config.repository');
        $this->blogSearchConfigId = $this->getBlogSearchConfigId();
        if (self::$initialized === false) {
            $this->initializeIndexing();
            self::$initialized = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->parentNameSearchState !== null) {
            $this->restoreParentNameSearch($this->parentNameSearchState);
            $this->parentNameSearchState = null;
        }
    }

    #[BeforeClass]
    public static function startTransactionBefore(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->beginTransaction();
    }

    #[AfterClass]
    public static function stopTransactionAfter(): void
    {
        $connection = KernelLifecycleManager::getKernel()
            ->getContainer()
            ->get(Connection::class);

        $connection->rollBack();
    }

    public function testFindingBlogsByTerm(): void
    {
        $browser = self::$browser;
        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request(
            'POST',
            '/store-api/search?search=Test-Blog'
        );
        static::assertIsString($browser->getResponse()->getContent());
        $response = \json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(15, $response['total']);
        static::assertSame('blog_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(15, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
    }

    public function testNotFindingBlogs(): void
    {
        $browser = self::$browser;
        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request(
            'POST',
            '/store-api/search?search=YAYY',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(0, $response['total']);
        static::assertSame('blog_listing', $response['apiAlias']);
        static::assertCount(0, $response['elements']);
    }

    public function testLimitOfTheCompressedCriteriaIsApplied(): void
    {
        $browser = self::$browser;
        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request('GET', '/store-api/search', [
            'limit' => 10,
            '_criteria' => self::compress(['search' => 'Test-Blog', 'limit' => 2]),
        ]);

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(15, $response['total']);
        static::assertCount(2, $response['elements'], 'The limit of the compressed criteria wins over the plain query parameter');
    }

    public function testSearchTermOfTheCompressedCriteriaIsReadBySuggest(): void
    {
        $browser = self::$browser;
        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request('GET', '/store-api/search-suggest', [
            '_criteria' => self::compress(['search' => 'Test-Blog']),
        ]);

        static::assertSame(200, $browser->getResponse()->getStatusCode(), (string) $browser->getResponse()->getContent());

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertSame(15, $response['total']);
    }

    public function testMissingSearchTermWithFilter(): void
    {
        $browser = self::$browser;
        $browser->request(
            'POST',
            '/store-api/search',
            [
                'manufacturer' => self::$ids->get('manufacturer'),
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertIsArray($response);
        static::assertArrayHasKey('total', $response);
        static::assertSame(1, $response['total']);

        static::assertArrayHasKey('apiAlias', $response);
        static::assertSame('blog_listing', $response['apiAlias']);

        static::assertArrayHasKey('elements', $response);
        static::assertIsArray($response['elements']);
        static::assertCount(1, $response['elements']);
        static::assertSame(self::$ids->get('manufacturer'), $response['elements'][0]['manufacturerId']);
    }

    public function testCriteriaFilterIsNotDuplicated(): void
    {
        $searchRoute = static::getContainer()->get(BlogSearchRoute::class);
        $criteriaValueResolver = static::getContainer()->get(CriteriaValueResolver::class);
        $requestCriteriaBuilder = static::getContainer()->get(RequestCriteriaBuilder::class);

        static::assertInstanceOf(RequestCriteriaBuilder::class, $requestCriteriaBuilder);

        $channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            'token',
            self::$ids->get('sales-channel')
        );

        $request = new Request([
            'search' => 'Test-Blog',
            'filter' => [
                ['type' => 'equals', 'field' => 'active', 'value' => true],
            ],
            'sort' => [
                ['field' => 'id', 'order' => FieldSorting::ASCENDING],
            ],
        ]);
        $request->setMethod(Request::METHOD_GET);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ENTITY, BlogDefinition::ENTITY_NAME);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $channelContext->getContext());

        $criteriaArguments = iterator_to_array(
            $criteriaValueResolver->resolve(
                $request,
                new ArgumentMetadata('criteria', Criteria::class, false, false, null)
            )
        );

        static::assertCount(1, $criteriaArguments);
        static::assertInstanceOf(Criteria::class, $criteriaArguments[0]);

        $originalCriteria = $criteriaArguments[0];

        $capturedCriteria = null;
        $listener = static function (BlogSearchCriteriaEvent $event) use (&$capturedCriteria): void {
            $capturedCriteria = clone $event->getCriteria();
        };

        EventHookDispatcher::fromContainer(static::getContainer())->on(BlogEvents::BLOG_SEARCH_CRITERIA, $listener);

        $searchRoute->load($request, $channelContext, clone $originalCriteria);

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertEquals($originalCriteria->getFilters(), $capturedCriteria->getFilters());
    }

    /**
     * @param array<string> $expected
     */
    #[DataProvider('searchOrCases')]
    public function testSearchOr(string $term, array $expected): void
    {
        $browser = self::$browser;
        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $this->proceedTestSearch($browser, $term, $expected);
    }

    /**
     * @param array<string> $expected
     */
    #[DataProvider('searchAndCases')]
    public function testSearchAnd(string $term, array $expected): void
    {
        $browser = self::$browser;
        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'andLogic' => true],
        ], Context::createDefaultContext());

        $this->proceedTestSearch($browser, $term, $expected);
    }

    public function testFindingBlogAlreadyHaveVariantsWithCustomSearchKeywords(): void
    {
        $browser = self::$browser;
        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request(
            'POST',
            '/store-api/search?search=bmw',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(2, $response['total']);
        static::assertSame('blog_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(2, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);

        $browser->request(
            'POST',
            '/store-api/search-suggest?search=bmw',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(2, $response['total']);
        static::assertSame('blog_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(2, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
    }

    public function testFindingBlogWhenAddedVariantsAfterSettingCustomSearchKeywords(): void
    {
        $browser = self::$browser;
        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request(
            'POST',
            '/store-api/search?search=volvo',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $response['total']);
        static::assertSame('blog_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(1, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);

        $browser->request(
            'POST',
            '/store-api/search-suggest?search=volvo',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $response['total']);
        static::assertSame('blog_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(1, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
    }

    public function testFindingBlogAlreadySetCustomSearchKeywordsWhenRemovedVariants(): void
    {
        $browser = self::$browser;
        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request(
            'POST',
            '/store-api/search?search=audi',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(2, $response['total']);
        static::assertSame('blog_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(2, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
    }

    public function testFindingBlogWithVariantsHaveDifferentKeyword(): void
    {
        $browser = self::$browser;
        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'andLogic' => false],
        ], Context::createDefaultContext());

        $browser->request(
            'POST',
            '/store-api/search?search=bmw',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(2, $response['total']);
        static::assertSame('blog_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(2, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);

        $browser->request(
            'POST',
            '/store-api/search?search=mercedes',
            [
            ]
        );

        $response = \json_decode((string) $browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);
        static::assertSame(1, $response['total']);
        static::assertSame('blog_listing', $response['apiAlias']);
        // Limited to max 10 entries
        static::assertCount(1, $response['elements']);
        static::assertSame('blog', $response['elements'][0]['apiAlias']);
    }

    public function testSearchFindsVariantByParentNameWhenFindBestVariantIsEnabled(): void
    {
        $ids = new IdsCollection();

        $blogRepository = static::getContainer()->get('blog.repository');
        static::assertInstanceOf(EntityRepository::class, $blogRepository);

        $languageRepository = static::getContainer()->get('language.repository');
        static::assertInstanceOf(EntityRepository::class, $languageRepository);

        $analyzer = static::getContainer()->get(BlogSearchKeywordAnalyzer::class);
        static::assertInstanceOf(BlogSearchKeywordAnalyzer::class, $analyzer);

        $searchKeywordUpdater = new SearchKeywordUpdater(
            static::getContainer()->get(Connection::class),
            $languageRepository,
            $blogRepository,
            $analyzer,
            new MockClock()
        );

        $this->enableParentNameSearch();
        $systemConfigService = static::getContainer()->get(SystemConfigService::class);
        $channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            'token',
            self::$ids->get('sales-channel')
        );
        $findBestVariant = $systemConfigService->get(
            'core.listing.findBestVariant',
            $channelContext->getChannelId()
        );

        try {
            $blogs = [
                (new BlogBuilder($ids, 'parent-variant-name'))
                    ->name('ticket 13976 parent name')
                    ->tax(null)
                    ->add('taxId', self::$ids->get('t1'))
                    ->price(10)
                    ->visibility(self::$ids->get('sales-channel'))
                    ->variant(
                        (new BlogBuilder($ids, 'parent-variant-name.1'))
                            ->name('child 1')
                            ->tax(null)
                            ->add('taxId', self::$ids->get('t1'))
                            ->price(11)
                            ->visibility(self::$ids->get('sales-channel'))
                            ->build()
                    )
                    ->variant(
                        (new BlogBuilder($ids, 'parent-variant-name.2'))
                            ->name('child 2')
                            ->tax(null)
                            ->add('taxId', self::$ids->get('t1'))
                            ->price(12)
                            ->visibility(self::$ids->get('sales-channel'))
                            ->build()
                    )
                    ->variant(
                        (new BlogBuilder($ids, 'parent-variant-name.3'))
                            ->name('child 3')
                            ->tax(null)
                            ->add('taxId', self::$ids->get('t1'))
                            ->price(13)
                            ->visibility(self::$ids->get('sales-channel'))
                            ->build()
                    )
                    ->build(),
            ];

            $blogRepository->create($blogs, Context::createDefaultContext());
            $blogRepository->update([
                [
                    'id' => $ids->get('parent-variant-name'),
                    'variantListingConfig' => [
                        'displayParent' => true,
                        'mainVariantId' => null,
                        'configuratorGroupConfig' => [],
                    ],
                ],
            ], Context::createDefaultContext());
            $searchKeywordUpdater->reset();
            $searchKeywordUpdater->update([
                $ids->get('parent-variant-name'),
                $ids->get('parent-variant-name.1'),
                $ids->get('parent-variant-name.2'),
                $ids->get('parent-variant-name.3'),
            ], Context::createDefaultContext());

            $systemConfigService->set(
                'core.listing.findBestVariant',
                true,
                $channelContext->getChannelId()
            );

            $searchRoute = static::getContainer()->get(BlogSearchRoute::class);
            $suggestRoute = static::getContainer()->get(BlogSuggestRoute::class);

            foreach ([$searchRoute, $suggestRoute] as $route) {
                $result = $route->load(
                    new Request(['search' => 'ticket 13976 parent name']),
                    $channelContext,
                    new Criteria()
                );

                static::assertSame(1, $result->getListingResult()->getTotal());

                $blog = $result->getListingResult()->getEntities()->first();
                static::assertInstanceOf(ChannelBlogEntity::class, $blog);
                static::assertNotSame($ids->get('parent-variant-name'), $blog->getId());
                static::assertSame($ids->get('parent-variant-name'), $blog->getParentId());
            }
        } finally {
            if ($findBestVariant === null) {
                $systemConfigService->delete('core.listing.findBestVariant', $channelContext->getChannelId());
            } else {
                $systemConfigService->set(
                    'core.listing.findBestVariant',
                    $findBestVariant,
                    $channelContext->getChannelId()
                );
            }

            $searchKeywordUpdater->reset();
        }
    }

    /**
     * @param array<array-key, bool> $searchTerms
     */
    #[DataProvider('searchTestCases')]
    public function testBlogSearch(string $blogNumber, array $searchTerms, ?string $languageId): void
    {
        $ids = self::$ids;
        if ($languageId === 'de-DE') {
            $languageId = $this->getDeDeLanguageId();
        }

        $searchRoute = static::getContainer()->get(BlogSearchRoute::class);
        $suggestRoute = static::getContainer()->get(BlogSuggestRoute::class);

        $channelContext = static::getContainer()->get(ChannelContextFactory::class)->create(
            'token',
            $ids->get('sales-channel'),
            [
                ChannelContextService::LANGUAGE_ID => $languageId ?? Defaults::LANGUAGE_SYSTEM,
            ]
        );

        foreach ($searchTerms as $searchTerm => $shouldBeFound) {
            $result = $searchRoute->load(
                new Request(['search' => $searchTerm]),
                $channelContext,
                new Criteria()
            );

            static::assertSame(
                $shouldBeFound,
                $result->getListingResult()->getEntities()->has($ids->get($blogNumber)),
                \sprintf(
                    'Blog was%s found, but should%s be found for term "%s".',
                    $result->getListingResult()->getEntities()->has($ids->get($blogNumber)) ? '' : ' not',
                    $shouldBeFound ? '' : ' not',
                    $searchTerm
                )
            );

            $result = $suggestRoute->load(
                new Request(['search' => $searchTerm]),
                $channelContext,
                new Criteria()
            );

            static::assertSame(
                $shouldBeFound,
                $result->getListingResult()->getEntities()->has($ids->get($blogNumber)),
                \sprintf(
                    'Blog was%s found, but should%s be found for term "%s".',
                    $result->getListingResult()->getEntities()->has($ids->get($blogNumber)) ? '' : ' not',
                    $shouldBeFound ? '' : ' not',
                    $searchTerm
                )
            );
        }
    }

    /**
     * @return array<string, array{string, array<array-key, bool>, string|null}>
     */
    public static function searchTestCases(): array
    {
        return [
            'test it finds blog' => [
                '1000',
                [
                    '1000' => true, // blogNumber
                    'Lorem' => true, // part of name
                    'ipsum' => true, // part of name
                    'Lorem ipsum' => true, // full name
                    'manufacturer' => true, // manufacturer
                    'dolor sit amet' => false, // full name but different language
                    'Hersteller' => false, // manufacturer but different language
                ],
                null,
            ],
            'test it finds blog by translation' => [
                '1000',
                [
                    '1000' => true, // blogNumber
                    'dolor' => true, // part of name
                    'sit' => true, // part of name
                    'amet' => true, // part of name
                    'dolor sit amet' => true, // full name
                    'Hersteller' => true, // manufacturer
                    'Lorem ipsum' => false, // full name but different language
                    'manufacturer' => false, // manufacturer but different language
                ],
                'de-DE',
            ],
            'test it finds blog by fallback translations' => [
                '1002',
                [
                    '1002' => true, // blogNumber
                    'Latin' => true, // part of name
                    'literature' => true, // part of name
                    'latin literature' => true, // full name
                ],
                'de-DE',
            ],
            'test it finds variant blog' => [
                '1000',
                [
                    '1000' => true, // blogNumber
                    'Lorem' => true, // part of name
                    'ipsum' => true, // part of name
                    'Lorem ipsum' => true, // full name
                    'manufacturer' => true, // manufacturer
                    'dolor sit amet' => false, // full name but different language
                    'Hersteller' => false, // manufacturer but different language
                    'consectetur adipiscing' => false, // full name but of parent language
                    'Suspendisse in' => false, // full name but of parent & different language
                    'varius' => false, // manufacturer but of parent
                    'Vestibulum' => false, // manufacturer but of parent & different language
                ],
                null,
            ],
            'test it finds variant blog by translation' => [
                '1000',
                [
                    '1000' => true, // blogNumber
                    'dolor' => true, // part of name
                    'sit' => true, // part of name
                    'amet' => true, // part of name
                    'dolor sit amet' => true, // full name
                    'Hersteller' => true, // manufacturer
                    'Lorem ipsum' => false, // full name but different language
                    'manufacturer' => false, // manufacturer but different language
                    'consectetur adipiscing' => false, // full name but of parent language
                    'Suspendisse in' => false, // full name but of parent & different language
                    'varius' => false, // manufacturer but of parent
                    'Vestibulum' => false, // manufacturer but of parent & different language
                ],
                'de-DE',
            ],
            'test it finds variant blog by parent translation' => [
                '1001.1',
                [
                    '1001' => true, // blogNumber
                    'Suspendisse' => true, // part of parent name
                    'Suspendisse in' => true, // full parent name
                    'Vestibulum' => true, // manufacturer
                    'Lorem ipsum' => false, // full name but different language
                    'consectetur adipiscing' => false, // full name but of parent language
                    'varius' => false, // manufacturer but of parent & different language
                ],
                'de-DE',
            ],
            'test it finds variant blog with inherited data' => [
                '1001.1',
                [
                    '1001' => true, // blogNumber
                    'consectetur' => true, // part of parent name
                    'adipiscing' => true, // part of parent name
                    'consectetur adipiscing' => true, // full parent name
                    'varius' => true, // parent manufacturer
                    'Suspendisse in' => false, // full name but different language
                    'Vestibulum' => false, // manufacturer but different language
                ],
                null,
            ],
        ];
    }

    /**
     * @return list<list<string>|mixed>
     */
    public static function searchAndCases(): array
    {
        return [
            [
                'Incredible Plastic Duoflex',
                ['Incredible Plastic Duoflex'],
            ],
            [
                'Incredible Plastic',
                ['Incredible Plastic Duoflex'],
            ],
            [
                'Incredible-%Plastic',
                ['Incredible Plastic Duoflex'],
            ],
            [
                'Incredible$^%&%$&$Plastic',
                ['Incredible Plastic Duoflex'],
            ],
            [
                '(๑★ .̫ ★๑)Incredible$^%&%$&$Plastic(๑★ .̫ ★๑)',
                ['Incredible Plastic Duoflex'],
            ],
            [
                '‰€€Incredible$^%&%$&$Plastic‰€€',
                ['Incredible Plastic Duoflex'],
            ],
            [
                '³²¼¼³¬½{¬]Incredible³²¼¼³¬½{¬]$^%&%$&$Plastic',
                ['Incredible Plastic Duoflex'],
            ],
            [
                'astic Concrete',
                ['Fantastic Concrete Comveyer'],
            ],
            [
                'astic cop',
                ['Rustic Copper Drastic Plastic', 'Fantastic Copper Ginger Vitro'],
            ],
            [
                '9095345345',
                [
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                'a b c d',
                [],
            ],
            [
                '@#%%#$ #$#@$ f@#$#$',
                [],
            ],
        ];
    }

    /**
     * @return list<list<string>|mixed>
     */
    public static function searchOrCases(): array
    {
        return [
            [
                'astic',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Concrete Comveyer',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                'Incredible Copper Vitro',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                'Incredible-Copper-Vitro',
                [],
            ],
            [
                'Incredible%$^$%^Copper%$^$^$%^Vitro',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                '(๑★ .̫ ★๑)Incredible%$^$%^Copper%$^$^$%^Vitro‰€€',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                '‰€€Incredible%$^$%^Copper%$^$^$%^Vitro‰€€',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                '³²¼¼³¬½{¬]Incredible%$^$%^Copper%$^$^$%^Vitro‰€€³²¼¼³¬½{¬]',
                [
                    'Rustic Copper Drastic Plastic',
                    'Incredible Plastic Duoflex',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                'Fantastic',
                [
                    'Fantastic Concrete Comveyer',
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                '9095345345',
                [
                    'Fantastic Copper Ginger Vitro',
                ],
            ],
            [
                'a b c d',
                [],
            ],
        ];
    }

    protected function initializeIndexing(): void
    {
        self::$ids = new IdsCollection();
        $ids = self::$ids;
        $this->createNavigationCategory($ids);

        self::$browser = $this->createCustomChannelBrowser([
            'id' => $ids->create('sales-channel'),
            'navigationCategoryId' => $ids->get('category'),
            'languages' => [['id' => Defaults::LANGUAGE_SYSTEM], ['id' => $this->getDeDeLanguageId()]],
        ]);

        $this->createGermanChannelDomain($ids);

        $this->setupBlogsForImplementSearch($ids);
    }

    /**
     * @param array<string, mixed> $expected
     */
    private function proceedTestSearch(KernelBrowser $browser, string $term, array $expected): void
    {
        $browser->request(
            'POST',
            '/store-api/search?search=' . $term,
            [
            ]
        );

        static::assertIsString($browser->getResponse()->getContent());
        $response = \json_decode($browser->getResponse()->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        $entities = $response['elements'];
        $resultBlogName = array_column($entities, 'name');

        sort($expected);
        sort($resultBlogName);

        static::assertSame($expected, $resultBlogName);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function compress(array $payload): string
    {
        $compressed = gzencode(json_encode($payload, \JSON_THROW_ON_ERROR));
        static::assertNotFalse($compressed, 'Gzip compressing failed');

        return Base64::urlEncode($compressed);
    }

    private function createNavigationCategory(IdsCollection $ids): void
    {
        $data = [
            'id' => $ids->create('category'),
            'name' => 'Test',
        ];

        static::getContainer()->get('category.repository')
            ->create([$data], Context::createDefaultContext());
    }

    private function setupBlogsForImplementSearch(IdsCollection $ids): void
    {
        /** @var EntityRepository<BlogCollection> $blogRepository */
        $blogRepository = static::getContainer()->get('blog.repository');
        $blogIds = [];
        $blogsNames = [
            'Rustic Copper Drastic Plastic' => '123123123',
            'Incredible Plastic Duoflex' => '765752342',
            'Fantastic Concrete Comveyer' => '834157484',
            'Fantastic Copper Ginger Vitro' => '9095345345',
        ];

        $blogs = [
            (new BlogBuilder($ids, 'bmw'))
                ->name(Uuid::randomHex())
                ->visibility($ids->get('sales-channel'))
                ->price(10, 9)
                ->manufacturer('Contena')
                ->add('customSearchKeywords', ['bmw'])
                ->variant(
                    (new BlogBuilder($ids, 'bmw.1'))
                        ->visibility($ids->get('sales-channel'))
                        ->build()
                )
                ->build(),
            // same as above, but has mercedes as variant
            (new BlogBuilder($ids, 'mercedes'))
                ->name(Uuid::randomHex())
                ->visibility($ids->get('sales-channel'))
                ->price(10, 9)
                ->manufacturer('Contena')
                ->add('customSearchKeywords', ['bmw'])
                ->variant(
                    (new BlogBuilder($ids, 'mercedes.1'))
                        ->visibility($ids->get('sales-channel'))
                        ->add('customSearchKeywords', ['bmw'])
                        ->build()
                )
                ->build(),
            // Add to a blog later variants
            (new BlogBuilder($ids, 'volvo'))
                ->name(Uuid::randomHex())
                ->visibility($ids->get('sales-channel'))
                ->price(10, 9)
                ->manufacturer('Contena')
                ->add('customSearchKeywords', ['volvo'])
                ->build(),
            (new BlogBuilder($ids, 'audi'))
                ->name(Uuid::randomHex())
                ->visibility($ids->get('sales-channel'))
                ->price(10, 9)
                ->manufacturer('Contena')
                ->add('customSearchKeywords', ['audi'])
                ->variant(
                    (new BlogBuilder($ids, 'audi.1'))
                        ->visibility($ids->get('sales-channel'))
                        ->build(),
                )
                ->variant(
                    (new BlogBuilder($ids, 'audi.2'))
                        ->visibility($ids->get('sales-channel'))
                        ->build()
                )
                ->variant(
                    (new BlogBuilder($ids, 'audi.3'))
                        ->visibility($ids->get('sales-channel'))
                        ->build()
                )
                ->build(),

            // search by term
            (new BlogBuilder($ids, '1000'))
                ->price(10)
                ->name('Lorem ipsum')
                ->translation($this->getDeDeLanguageId(), 'name', 'dolor sit amet')
                ->visibility($ids->get('sales-channel'))
                ->manufacturer('manufacturer', [$this->getDeDeLanguageId() => ['name' => 'Hersteller']])
                ->build(),

            (new BlogBuilder($ids, '1001'))
                ->name('consectetur adipiscing')
                ->translation($this->getDeDeLanguageId(), 'name', 'Suspendisse in')
                ->price(5)
                ->visibility($ids->get('sales-channel'))
                ->manufacturer('varius', [$this->getDeDeLanguageId() => ['name' => 'Vestibulum']])
                ->variant(
                    (new BlogBuilder($ids, '1001.1'))
                        ->price(10)
                        ->name(null)
                        ->visibility($ids->get('sales-channel'))
                        ->build()
                )
                ->build(),

            (new BlogBuilder($ids, '1002'))
                ->price(10)
                ->name('Latin literature')
                ->visibility($ids->get('sales-channel'))
                ->build(),
        ];

        foreach ($blogsNames as $name => $number) {
            $blogs[] = (new BlogBuilder($ids, $number))
                ->name($name)
                ->stock(1)
                ->price(19.99, 10)
                ->manufacturer('Contena')
                ->tax('15', 15)
                ->category('random cat')
                ->visibility($ids->get('sales-channel'))
                ->build();
        }

        for ($i = 1; $i <= 15; ++$i) {
            $blogs[] = (new BlogBuilder($ids, 'blog' . $i))
                ->name('Test-Blog')
                ->manufacturer('test-' . $i)
                ->active(true)
                ->price(15, 10)
                ->tax('test', 15)
                ->visibility($ids->get('sales-channel'))
                ->build();
        }

        $blogRepository->create($blogs, Context::createDefaultContext());

        $this->searchKeywordUpdater->update($blogIds, Context::createDefaultContext());

        $this->blogSearchConfigRepository->update([
            ['id' => $this->blogSearchConfigId, 'minSearchLength' => 3],
        ], Context::createDefaultContext());

        $blogRepository->create([
            (new BlogBuilder($ids, 'volvo.1'))
                ->visibility($ids->get('sales-channel'))
                ->parent('volvo')
                ->build(),
        ], Context::createDefaultContext());
    }

    private function enableParentNameSearch(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        /** @var array<string, string> $originalState */
        $originalState = $connection->fetchAllKeyValue(
            'SELECT LOWER(HEX(id)), searchable FROM blog_search_config_field WHERE field = :field',
            ['field' => 'parent.name']
        );

        (new Migration1775460999AddParentNameToBlogSearchConfig())->update($connection);

        $connection->executeStatement(
            'UPDATE blog_search_config_field SET searchable = 1 WHERE field = :field',
            ['field' => 'parent.name']
        );

        $this->parentNameSearchState = $originalState;
    }

    /**
     * @param array<string, string> $originalState
     */
    private function restoreParentNameSearch(array $originalState): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $parentNameConfigIds = array_map(
            'strval',
            $connection->fetchFirstColumn(
                'SELECT LOWER(HEX(id)) FROM blog_search_config_field WHERE field = :field',
                ['field' => 'parent.name']
            )
        );

        $addedConfigIds = array_values(array_diff($parentNameConfigIds, array_keys($originalState)));
        if ($addedConfigIds !== []) {
            $connection->executeStatement(
                'DELETE FROM blog_search_config_field WHERE id IN (:ids)',
                ['ids' => Uuid::fromHexToBytesList($addedConfigIds)],
                ['ids' => ArrayParameterType::BINARY]
            );
        }

        foreach ($originalState as $id => $searchable) {
            $connection->executeStatement(
                'UPDATE blog_search_config_field SET searchable = :searchable WHERE id = :id',
                [
                    'id' => Uuid::fromHexToBytes($id),
                    'searchable' => (int) $searchable,
                ]
            );
        }

        $this->searchKeywordUpdater->reset();
    }

    private function getBlogSearchConfigId(): string
    {
        $criteria = new Criteria();
        $criteria->addFilter(
            new EqualsFilter('languageId', Context::createDefaultContext()->getLanguageId())
        );

        $firstId = $this->blogSearchConfigRepository->searchIds($criteria, Context::createDefaultContext())->firstId();

        static::assertIsString($firstId);

        return $firstId;
    }

    private function createGermanChannelDomain(IdsCollection $ids): void
    {
        static::getContainer()->get('language.repository')->upsert([
            [
                'id' => $this->getDeDeLanguageId(),
                'channelDomains' => [
                    [
                        'channelId' => $ids->get('sales-channel'),
                        'currencyId' => Defaults::CURRENCY,
                        'snippetSetId' => $this->getSnippetSetIdForLocale('de-DE'),
                        'url' => $_SERVER['APP_URL'] . '/de',
                    ],
                ],
            ],
        ], Context::createDefaultContext());
    }
}
