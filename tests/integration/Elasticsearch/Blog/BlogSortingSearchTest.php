<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Elasticsearch\Blog;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\System\CustomField\CustomFieldTypes;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Contena\Elasticsearch\Framework\ElasticsearchHelper;
use Contena\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Contena\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Contena\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Contena\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class BlogSortingSearchTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;

    private Client $client;

    private BlogDefinition $blogDefinition;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private ElasticsearchHelper $helper;

    private ElasticsearchOutdatedIndexDetector $indexDetector;

    private IdsCollection $ids;

    private Connection $connection;

    private Context $context;

    /**
     * Built once for the whole class by the first run of setUp(). The first-test-indexes pattern was
     * replaced by guarded setUp so the suite no longer depends on test execution order.
     */
    private static IdsCollection $indexedIds;

    protected function setUp(): void
    {
        $this->helper = static::getContainer()->get(ElasticsearchHelper::class);
        $this->client = static::getContainer()->get(Client::class);
        $this->blogDefinition = static::getContainer()->get(BlogDefinition::class);
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->indexDetector = static::getContainer()->get(ElasticsearchOutdatedIndexDetector::class);
        $this->connection = static::getContainer()->get(Connection::class);
        $this->context = Context::createDefaultContext();
        $this->ids = new IdsCollection();

        parent::setUp();

        if (!isset(self::$indexedIds)) {
            self::$indexedIds = $this->buildIndex();
        }
    }

    #[AfterClass]
    public static function cleanup(): void
    {
        $container = KernelLifecycleManager::getKernel()->getContainer();

        $connection = $container->get(Connection::class);

        $connection->executeStatement('DELETE FROM blog');
        $connection->executeStatement('DELETE FROM blog_sorting WHERE url_key LIKE :key', ['key' => 'ss-test-%']);

        $connection->executeStatement('DELETE FROM custom_field WHERE name LIKE :name', ['name' => 'ss\_test\_%']);
        $connection->executeStatement('DELETE FROM custom_field_set WHERE name = :name', ['name' => 'sorting_search_set']);

        $connection->executeStatement('DELETE FROM elasticsearch_index_task');
    }

    public function testCustomFieldMappingsExist(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $allIndices = $this->indexDetector->getAllUsedIndices();
        static::assertNotEmpty($allIndices, 'No ES indices found. Keys: ' . implode(', ', array_keys($allIndices)));

        $indexName = array_keys($allIndices)[0];

        $indices = array_values($this->client->indices()->getMapping(['index' => $indexName]))[0];
        $properties = $indices['mappings']['properties']['customFields']['properties'] ?? [];

        static::assertArrayHasKey(
            Defaults::LANGUAGE_SYSTEM,
            $properties,
            'Language system key not found. Available keys: ' . implode(', ', array_keys($properties))
        );
        $languageProperties = $properties[Defaults::LANGUAGE_SYSTEM]['properties'];
        static::assertIsArray($languageProperties);

        static::assertArrayHasKey('ss_test_int', $languageProperties);
        static::assertSame('long', $languageProperties['ss_test_int']['type']);

        static::assertArrayHasKey('ss_test_float', $languageProperties);
        static::assertSame('double', $languageProperties['ss_test_float']['type']);

        static::assertArrayHasKey('ss_test_text', $languageProperties);
        static::assertSame('keyword', $languageProperties['ss_test_text']['type']);

        static::assertArrayHasKey('ss_test_bool', $languageProperties);
        static::assertSame('boolean', $languageProperties['ss_test_bool']['type']);
    }

    public function testSortByCustomFieldIntAsc(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new FieldSorting('customFields.ss_test_int', FieldSorting::ASCENDING));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->blogDefinition, $criteria, $this->context)->getIds();

        // Expected order by ss_test_int ASC: blog-4 (50), blog-1 (100), blog-2 (200), blog-3 (300)
        static::assertSame($ids->get('blog-4'), $result[0]);
        static::assertSame($ids->get('blog-1'), $result[1]);
        static::assertSame($ids->get('blog-2'), $result[2]);
        static::assertSame($ids->get('blog-3'), $result[3]);
    }

    public function testSortByCustomFieldIntDesc(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new FieldSorting('customFields.ss_test_int', FieldSorting::DESCENDING));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->blogDefinition, $criteria, $this->context)->getIds();

        // Expected order by ss_test_int DESC: blog-3 (300), blog-2 (200), blog-1 (100), blog-4 (50)
        static::assertSame($ids->get('blog-3'), $result[0]);
        static::assertSame($ids->get('blog-2'), $result[1]);
        static::assertSame($ids->get('blog-1'), $result[2]);
        static::assertSame($ids->get('blog-4'), $result[3]);
    }

    public function testSortByCustomFieldFloatDesc(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new FieldSorting('customFields.ss_test_float', FieldSorting::DESCENDING));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->blogDefinition, $criteria, $this->context)->getIds();

        // Expected order by ss_test_float DESC: blog-4 (3.5), blog-2 (2.5), blog-1 (1.5), blog-3 (0.5)
        static::assertSame($ids->get('blog-4'), $result[0]);
        static::assertSame($ids->get('blog-2'), $result[1]);
        static::assertSame($ids->get('blog-1'), $result[2]);
        static::assertSame($ids->get('blog-3'), $result[3]);
    }

    public function testFilterByCustomFieldTextEquals(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.ss_test_text', 'alpha'));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->blogDefinition, $criteria, $this->context);

        // blog-1 and blog-3 have ss_test_text = 'alpha'
        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-1')));
        static::assertTrue($result->has($ids->get('blog-3')));
    }

    public function testFilterByCustomFieldBoolTrue(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.ss_test_bool', true));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->blogDefinition, $criteria, $this->context);

        // blog-1 and blog-3 have ss_test_bool = true
        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-1')));
        static::assertTrue($result->has($ids->get('blog-3')));
    }

    public function testFilterByCustomFieldBoolFalse(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.ss_test_bool', false));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->blogDefinition, $criteria, $this->context);

        // blog-2 and blog-4 have ss_test_bool = false
        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-2')));
        static::assertTrue($result->has($ids->get('blog-4')));
    }

    public function testFilterByCustomFieldIntRange(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new RangeFilter('customFields.ss_test_int', [
            RangeFilter::GTE => 100,
            RangeFilter::LTE => 200,
        ]));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->blogDefinition, $criteria, $this->context);

        // blog-1 (100) and blog-2 (200) are in range [100, 200]
        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-1')));
        static::assertTrue($result->has($ids->get('blog-2')));
    }

    public function testFilterByCustomFieldIntExact(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.ss_test_int', 300));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->blogDefinition, $criteria, $this->context);

        // Only blog-3 has ss_test_int = 300
        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-3')));
    }

    public function testFilterByCustomFieldFloatRange(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new RangeFilter('customFields.ss_test_float', [
            RangeFilter::GT => 1.0,
            RangeFilter::LT => 3.0,
        ]));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->blogDefinition, $criteria, $this->context);

        // blog-1 (1.5) and blog-2 (2.5) are in range (1.0, 3.0)
        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-1')));
        static::assertTrue($result->has($ids->get('blog-2')));
    }

    public function testCombinedFilterAndSort(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.ss_test_text', 'alpha'));
        $criteria->addSorting(new FieldSorting('customFields.ss_test_int', FieldSorting::ASCENDING));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->blogDefinition, $criteria, $this->context)->getIds();

        // Filtered to alpha (blog-1, blog-3), sorted by int ASC (100, 300)
        static::assertCount(2, $result);
        static::assertSame($ids->get('blog-1'), $result[0]);
        static::assertSame($ids->get('blog-3'), $result[1]);
    }

    public function testCombinedBoolFilterAndFloatSort(): void
    {
        $ids = self::$indexedIds;

        $this->ids = $ids;

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.ss_test_bool', false));
        $criteria->addSorting(new FieldSorting('customFields.ss_test_float', FieldSorting::ASCENDING));

        $searcher = $this->createEntitySearcher();

        $result = $searcher->search($this->blogDefinition, $criteria, $this->context)->getIds();

        // Filtered to bool=false (blog-2, blog-4), sorted by float ASC (2.5, 3.5)
        static::assertCount(2, $result);
        static::assertSame($ids->get('blog-2'), $result[0]);
        static::assertSame($ids->get('blog-4'), $result[1]);
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    protected function runWorker(): void
    {
    }

    private function buildIndex(): IdsCollection
    {
        $this->connection->executeStatement('DELETE FROM blog');

        $this->clearElasticsearch();

        $this->connection->executeStatement('DELETE FROM blog_sorting WHERE url_key LIKE :key', ['key' => 'ss-test-%']);
        $this->connection->executeStatement('DELETE FROM custom_field');

        // Create the ES index first (empty, no custom fields yet)
        $command = new ElasticsearchIndexingCommand(
            static::getContainer()->get(ElasticsearchIndexer::class),
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get(CreateAliasTaskHandler::class),
            true
        );

        $command->run(new ArrayInput([]), new NullOutput());

        static::assertNotEmpty($this->indexDetector->getAllUsedIndices());

        // Create custom fields
        $customFieldRepository = static::getContainer()->get('custom_field_set.repository');

        $customFieldRepository->create([
            [
                'id' => $this->ids->get('custom-field-set'),
                'name' => 'sorting_search_set',
                'config' => [
                    'label' => [
                        'en-GB' => 'Sorting Search Test Set',
                    ],
                ],
                'relations' => [
                    ['entityName' => 'blog'],
                ],
                'customFields' => [
                    [
                        'name' => 'ss_test_int',
                        'type' => CustomFieldTypes::INT,
                    ],
                    [
                        'name' => 'ss_test_float',
                        'type' => CustomFieldTypes::FLOAT,
                    ],
                    [
                        'name' => 'ss_test_text',
                        'type' => CustomFieldTypes::TEXT,
                        'includeInSearch' => true,
                    ],
                    [
                        'name' => 'ss_test_bool',
                        'type' => CustomFieldTypes::BOOL,
                        'includeInSearch' => true,
                    ],
                ],
            ],
        ], $this->context);

        $sortingRepository = static::getContainer()->get('blog_sorting.repository');
        $sortingRepository->create([
            [
                'id' => $this->ids->get('sorting-by-int'),
                'key' => 'ss-test-sorting-int',
                'priority' => 1,
                'active' => true,
                'fields' => [
                    ['field' => 'customFields.ss_test_int', 'order' => 'asc', 'priority' => 1, 'naturalSorting' => false],
                ],
                'label' => 'Sort by ss_test_int',
            ],
            [
                'id' => $this->ids->get('sorting-by-float'),
                'key' => 'ss-test-sorting-float',
                'priority' => 2,
                'active' => true,
                'fields' => [
                    ['field' => 'customFields.ss_test_float', 'order' => 'desc', 'priority' => 1, 'naturalSorting' => false],
                ],
                'label' => 'Sort by ss_test_float',
            ],
        ], $this->context);

        // Reset the custom field types cache so the next getCustomFieldTypes() call
        // queries the DB (the cache was populated as empty during index creation before custom fields existed)
        $utils = static::getContainer()->get(ElasticsearchIndexingUtils::class);
        new \ReflectionProperty(ElasticsearchIndexingUtils::class, 'customFieldsTypes')->setValue($utils, []);

        $blogs = [
            new BlogBuilder($this->ids, 'blog-1')
                ->name('Blog Alpha')
                ->visibility(TestDefaults::CHANNEL)
                ->customField('ss_test_int', 100)
                ->customField('ss_test_float', 1.5)
                ->customField('ss_test_text', 'alpha')
                ->customField('ss_test_bool', true)
                ->build(),
            new BlogBuilder($this->ids, 'blog-2')
                ->name('Blog Beta')
                ->visibility(TestDefaults::CHANNEL)
                ->customField('ss_test_int', 200)
                ->customField('ss_test_float', 2.5)
                ->customField('ss_test_text', 'beta')
                ->customField('ss_test_bool', false)
                ->build(),
            new BlogBuilder($this->ids, 'blog-3')
                ->name('Blog Gamma')
                ->visibility(TestDefaults::CHANNEL)
                ->customField('ss_test_int', 300)
                ->customField('ss_test_float', 0.5)
                ->customField('ss_test_text', 'alpha')
                ->customField('ss_test_bool', true)
                ->build(),
            new BlogBuilder($this->ids, 'blog-4')
                ->name('Blog Delta')
                ->visibility(TestDefaults::CHANNEL)
                ->customField('ss_test_int', 50)
                ->customField('ss_test_float', 3.5)
                ->customField('ss_test_text', 'delta')
                ->customField('ss_test_bool', false)
                ->build(),
        ];

        $this->blogRepository->create($blogs, $this->context);

        // Index the blogs into ES using updateIds
        $indexer = static::getContainer()->get(ElasticsearchIndexer::class);
        $indexer->updateIds(
            $this->blogDefinition,
            [
                $this->ids->get('blog-1'),
                $this->ids->get('blog-2'),
                $this->ids->get('blog-3'),
                $this->ids->get('blog-4'),
            ],
            $this->context,
        );

        $index = $this->helper->getIndexName($this->blogDefinition);

        $exists = $this->client->indices()->exists(['index' => $index]);
        static::assertTrue($exists, 'Expected elasticsearch indices present');

        return $this->ids;
    }
}
