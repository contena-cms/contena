<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Core\Content\ContentStream\Service;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingLoader;
use Contena\Core\Content\ContentStream\Exception\NoFilterException;
use Contena\Core\Content\ContentStream\Service\AbstractBlogStreamBuilder;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilder;
use Contena\Core\Content\ContentStream\Service\ContentStreamBuilderInterface;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\Feature;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;

/**
 * @internal
 */
class ContentStreamBuilderTest extends TestCase
{
    use EnvTestBehaviour;
    use IntegrationTestBehaviour;
    use TaxAddToChannelTestBehaviour;

    /**
     * @var ChannelRepository<BlogCollection>
     */
    private ChannelRepository $blogRepository;

    private Context $context;

    private ChannelContext $channelContext;

    private AbstractBlogStreamBuilder&ContentStreamBuilderInterface $service;

    protected function setUp(): void
    {
        $this->context = Context::createDefaultContext();
        $this->service = static::getContainer()->get(ContentStreamBuilder::class);
        $this->blogRepository = static::getContainer()->get('channel.blog.repository');

        $channelContextFactory = static::getContainer()->get(ChannelContextFactory::class);
        $this->channelContext = $channelContextFactory->create(Uuid::randomHex(), TestDefaults::CHANNEL);
    }

    public function testBuildFilters(): void
    {
        $this->createTestEntity();

        $blogs = $this->getBlogs('137b079935714281ba80b40f83f8d7eb');

        static::assertCount(2, $blogs);
    }

    public function testBuildAddsDirectVariantStateWhenDisplayAsGroupIsFalse(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get('content_stream.repository')->create([[
            'id' => $ids->get('stream'),
            'name' => 'direct-variants',
            'displayAsGroup' => false,
            'filters' => [[
                'type' => 'equals',
                'field' => 'blog.id',
                'value' => Uuid::randomHex(),
            ]],
        ]], Context::createDefaultContext());

        $criteria = new Criteria();
        $this->service->enrichCriteria($criteria, $ids->get('stream'), Context::createDefaultContext());

        static::assertTrue($criteria->hasState(BlogListingLoader::STATE_SKIP_ADD_GROUPING));
        static::assertCount(1, $criteria->getFilters());
    }

    public function testBuildKeepsDisplayAsGroupEnabledWhenPersistedFlagIsTrue(): void
    {
        $ids = new IdsCollection();

        static::getContainer()->get('content_stream.repository')->create([[
            'id' => $ids->get('stream'),
            'name' => 'grouped-variants',
            'displayAsGroup' => true,
            'filters' => [[
                'type' => 'equals',
                'field' => 'blog.id',
                'value' => Uuid::randomHex(),
            ]],
        ]], Context::createDefaultContext());

        $criteria = new Criteria();
        $this->service->enrichCriteria($criteria, $ids->get('stream'), Context::createDefaultContext());

        static::assertFalse($criteria->hasState(BlogListingLoader::STATE_SKIP_ADD_GROUPING));
        static::assertCount(1, $criteria->getFilters());
    }

    public function testNestedFilters(): void
    {
        $ids = new IdsCollection();

        $stream = [
            'id' => $ids->get('stream'),
            'name' => 'test2',
            'filters' => [
                [
                    'type' => 'multi',
                    'operator' => 'OR',
                    'position' => 0,
                    'queries' => [
                        [
                            'type' => 'multi',
                            'operator' => 'AND',
                            'position' => 0,
                            'queries' => [
                                [
                                    'type' => 'contains',
                                    'field' => 'name',
                                    'value' => 'Awesome',
                                    'position' => 0,
                                ],
                                [
                                    'type' => 'not',
                                    'position' => 1,
                                    'queries' => [
                                        [
                                            'type' => 'contains',
                                            'field' => 'name',
                                            'value' => 'Copper',
                                            'position' => 0,
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        static::getContainer()->get('content_stream.repository')
            ->create([$stream], Context::createDefaultContext());

        $criteria = new Criteria();
        $this->service->enrichCriteria($criteria, $ids->get('stream'), Context::createDefaultContext());

        $expected = new MultiFilter(MultiFilter::CONNECTION_OR, [
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                new ContainsFilter('blog.name', 'Awesome'),
                new NotFilter(MultiFilter::CONNECTION_AND, [
                    new ContainsFilter('blog.name', 'Copper'),
                ]),
            ]),
        ]);

        $filters = $criteria->getFilters();
        $filter = array_shift($filters);
        static::assertInstanceOf(MultiFilter::class, $filter);

        static::assertEquals($expected, $filter);
    }

    #[IgnoreDeprecations]
    public function testDeprecatedBuildFiltersTriggersDeprecationAndStillBuildsFilters(): void
    {
        // Before v6.8.0.0 the deprecated fallback (used for builders that do not extend
        // AbstractBlogStreamBuilder) must stay functional and emit a deprecation notice; TESTS_RUNNING is
        // disabled so the notice is triggered instead of suppressed. Once the flag is active it throws
        // instead, which the unit test covers. #[DisabledFeatures] has no effect in integration tests
        // (the FeatureFlagExtension only covers the unit-test namespace), so skip explicitly.
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $this->setEnvVars(['TESTS_RUNNING' => false]);

        $ids = new IdsCollection();
        $value = Uuid::randomHex();

        static::getContainer()->get('content_stream.repository')->create([[
            'id' => $ids->get('stream'),
            'name' => 'bc-build-filters',
            'filters' => [[
                'type' => 'equals',
                'field' => 'blog.id',
                'value' => $value,
            ]],
        ]], Context::createDefaultContext());

        $this->expectUserDeprecationMessage(
            'Method "' . ContentStreamBuilder::class . '::buildFilters()" is deprecated and will be removed in v6.8.0.0. Use "AbstractBlogStreamBuilder::enrichCriteria" instead.'
        );

        $filters = $this->service->buildFilters($ids->get('stream'), Context::createDefaultContext());

        static::assertCount(1, $filters);
    }

    public function testNoFilters(): void
    {
        $this->createTestEntityWithoutFilters();

        $this->expectException(NoFilterException::class);

        $this->getBlogs('137b079935714281ba80b40f83f8d7eb');
    }

    /**
     * @param list<string> $releaseDates
     */
    #[DataProvider('relativeTimeFiltersDataProvider')]
    public function testRelativeTimeFilters(string $type, string $operator, string $field, string $value, array $releaseDates, int $expected): void
    {
        $ids = new IdsCollection();

        $stream = [
            'id' => $ids->get('stream2'),
            'name' => 'test3',
            'filters' => [
                [
                    'type' => $type,
                    'field' => $field,
                    'value' => $value,
                    'parameters' => [
                        'operator' => $operator,
                    ],
                ],
            ],
        ];

        static::getContainer()->get('content_stream.repository')
            ->create([$stream], Context::createDefaultContext());

        $this->createBlogs($releaseDates);

        $blogs = $this->getBlogs($ids->get('stream2'));

        static::assertCount($expected, $blogs);
    }

    /**
     * @return iterable<string, array{0: string, 1: string, 2: string, 3: string, 4: list<string>, 5: int}>
     */
    public static function relativeTimeFiltersDataProvider(): iterable
    {
        yield 'days until - gt' => ['until', 'gt', 'releaseDate', 'P5D', self::getReleaseDates('+'), 3];
        yield 'days until - lt' => ['until', 'lt', 'releaseDate', 'P5D', self::getReleaseDates('+'), 5];
        yield 'days until - gte' => ['until', 'gte', 'releaseDate', 'P5D', self::getReleaseDates('+'), 5];
        yield 'days until - lte' => ['until', 'lte', 'releaseDate', 'P5D', self::getReleaseDates('+'), 7];
        yield 'days until - eq' => ['until', 'eq', 'releaseDate', 'P5D', self::getReleaseDates('+'), 2];
        yield 'days until - neq' => ['until', 'neq', 'releaseDate', 'P5D', self::getReleaseDates('+'), 8];
        yield 'days since - gt' => ['since', 'gt', 'releaseDate', 'P5D', self::getReleaseDates('-'), 3];
        yield 'days since - lt' => ['since', 'lt', 'releaseDate', 'P5D', self::getReleaseDates('-'), 5];
        yield 'days since - gte' => ['since', 'gte', 'releaseDate', 'P5D', self::getReleaseDates('-'), 5];
        yield 'days since - lte' => ['since', 'lte', 'releaseDate', 'P5D', self::getReleaseDates('-'), 7];
        yield 'days since - eq' => ['since', 'eq', 'releaseDate', 'P5D', self::getReleaseDates('-'), 2];
        yield 'days since - neq' => ['since', 'neq', 'releaseDate', 'P5D', self::getReleaseDates('-'), 8];
    }

    private function getBlogs(string $contentStreamId): BlogCollection
    {
        $criteria = new Criteria();
        $this->service->enrichCriteria($criteria, $contentStreamId, $this->context);

        return $this->blogRepository->search($criteria, $this->channelContext)->getEntities();
    }

    private function createTestEntity(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $randomBlogIds = implode('|', \array_slice(array_column($this->createBlogs(), 'id'), 0, 2));

        $connection->executeStatement(
            "
            INSERT INTO `content_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('137B079935714281BA80B40F83F8D7EB'), '[{\"type\": \"multi\", \"queries\": [{\"type\": \"multi\", \"queries\": [{\"type\": \"equalsAny\", \"field\": \"blog.id\", \"value\": \"{$randomBlogIds}\"}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"blog.width\", \"parameters\": {\"gte\": 221, \"lte\": 932}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"range\", \"field\": \"blog.width\", \"parameters\": {\"lte\": 245}}], \"operator\": \"AND\"}, {\"type\": \"multi\", \"queries\": [{\"type\": \"equals\", \"field\": \"blog.manufacturer.id\", \"value\": \"02f6b9aa385d4f40aaf573661b2cf919\"}, {\"type\": \"range\", \"field\": \"blog.height\", \"parameters\": {\"gte\": 182}}], \"operator\": \"AND\"}], \"operator\": \"OR\"}]', 0, '2019-08-16 08:43:57.488', NULL);
        "
        );

        $connection->executeStatement(
            "
            INSERT INTO `content_stream_filter` (`id`, `content_stream_id`, `parent_id`, `type`, `field`, `operator`, `value`, `parameters`, `position`, `custom_fields`, `created_at`, `updated_at`)
            VALUES
                (UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), UNHEX('137B079935714281BA80B40F83F8D7EB'), NULL, 'multi', NULL, 'OR', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.469', NULL),
                (UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 1, NULL, '2019-08-16 08:43:57.478', NULL),
                (UNHEX('272B4392E7B34EF2ABB4827A33630C1D'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 3, NULL, '2019-08-16 08:43:57.486', NULL),
                (UNHEX('4A7AEB36426A482A8BFFA049F795F5E7'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 0, NULL, '2019-08-16 08:43:57.470', NULL),
                (UNHEX('BB87D86524FB4E7EA01EE548DD43A5AC'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('DA6CD9776BC84463B25D5B6210DDB57B'), 'multi', NULL, 'AND', NULL, NULL, 2, NULL, '2019-08-16 08:43:57.483', NULL),
                (UNHEX('56C5DF0B41954334A7B0CDFEDFE1D7E9'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('272B4392E7B34EF2ABB4827A33630C1D'), 'range', 'width', NULL, NULL, '{\"lte\":932,\"gte\":221}', 1, NULL, '2019-08-16 08:43:57.488', NULL),
                (UNHEX('6382E03A768F444E9C2A809C63102BD4'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('BB87D86524FB4E7EA01EE548DD43A5AC'), 'range', 'height', NULL, NULL, '{\"gte\":182}', 2, NULL, '2019-08-16 08:43:57.485', NULL),
                (UNHEX('7CBC1236ABCD43CAA697E9600BF1DF6E'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('4A7AEB36426A482A8BFFA049F795F5E7'), 'range', 'width', NULL, NULL, '{\"lte\":245}', 1, NULL, '2019-08-16 08:43:57.476', NULL),
                (UNHEX('80B2B90171454467B769A4C161E74B87'), UNHEX('137B079935714281BA80B40F83F8D7EB'), UNHEX('0EE60B6A87774E9884A832D601BE6B8F'), 'equalsAny', 'id', NULL, '{$randomBlogIds}', NULL, 1, NULL, '2019-08-16 08:43:57.480', NULL);
    "
        );
    }

    private function createTestEntityWithoutFilters(): void
    {
        $connection = static::getContainer()->get(Connection::class);

        $connection->executeStatement(
            '
            INSERT INTO `content_stream` (`id`, `api_filter`, `invalid`, `created_at`, `updated_at`)
            VALUES
                (UNHEX(\'137B079935714281BA80B40F83F8D7EB\'), \'[]\', 0, \'2019-08-16 08:43:57.488\', NULL);
        '
        );
    }

    /**
     * @param list<string>|null $releaseDates
     *
     * @return list<array<string, mixed>>
     */
    private function createBlogs(?array $releaseDates = null): array
    {
        $manufacturerId = Uuid::randomHex();
        $taxId = Uuid::randomHex();
        $channelId = TestDefaults::CHANNEL;
        $blogs = [];

        for ($i = 0; $i < 10; ++$i) {
            $blogs[] = [
                'id' => Uuid::randomHex(),
                'blogNumber' => Uuid::randomHex(),
                'stock' => 1,
                'name' => 'Test',
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 10, 'net' => 9, 'linked' => false]],
                'manufacturer' => ['id' => $manufacturerId, 'name' => 'test'],
                'tax' => ['id' => $taxId, 'taxRate' => 17, 'name' => 'with id'],
                'visibilities' => [
                    ['channelId' => $channelId, 'visibility' => BlogVisibilityDefinition::VISIBILITY_ALL],
                ],
                'releaseDate' => $releaseDates ? $releaseDates[$i] : null,
            ];
        }

        static::getContainer()->get('blog.repository')->create($blogs, $this->context);
        $lastBlog = end($blogs);
        static::assertIsArray($lastBlog);
        $this->addTaxDataToChannel($this->channelContext, $lastBlog['tax']);

        return $blogs;
    }

    /**
     * @return list<string>
     */
    private static function getReleaseDates(string $operator): array
    {
        return [
            (new \DateTimeImmutable())->modify($operator . '8 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '5 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '9 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '12 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '4 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '3 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '5 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '1 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '2 days')->format('Y-m-d'),
            (new \DateTimeImmutable())->modify($operator . '3 days')->format('Y-m-d'),
        ];
    }
}
