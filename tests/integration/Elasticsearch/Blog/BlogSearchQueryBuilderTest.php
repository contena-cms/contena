<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Elasticsearch\Blog;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\Attributes\BeforeClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Test\TestCaseBase\CacheTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\FilesystemBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\SessionTestBehaviour;
use Contena\Core\System\CustomField\CustomFieldService;
use Contena\Core\System\CustomField\CustomFieldTypes;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Elasticsearch\Event\ElasticsearchCustomFieldsMappingEvent;
use Contena\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Contena\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class BlogSearchQueryBuilderTest extends TestCase
{
    use CacheTestBehaviour;
    use ChannelApiTestBehaviour;
    use ElasticsearchTestTestBehaviour;
    use FilesystemBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;
    use SessionTestBehaviour;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private Connection $connection;

    private CustomFieldService $customFieldService;

    /**
     * Built once for the whole class by the first run of setUp(). The first-test-indexes pattern was
     * replaced by guarded setUp because a data-provided test (testSearch) can no longer also receive
     * the ids via #[Depends] - see NoDependsWithDataProviderRule.
     */
    private static IdsCollection $indexedIds;

    protected function setUp(): void
    {
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->connection = static::getContainer()->get(Connection::class);
        $this->customFieldService = static::getContainer()->get(CustomFieldService::class);

        if (!isset(self::$indexedIds)) {
            self::$indexedIds = $this->buildIndex();
        }
    }

    protected function tearDown(): void
    {
        $this->customFieldService->reset();
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

    public function testAndSearch(): void
    {
        $ids = self::$indexedIds;

        $this->setSearchConfiguration(true, ['name']);
        $this->setSearchScores([]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('Aerodynamic Leather');
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->blogRepository->searchIds($criteria, Context::createDefaultContext());
        $resultIds = $result->getIds();

        static::assertCount(3, $resultIds, 'But got ' . $ids->getKeys($resultIds));

        static::assertSame(
            [
                $ids->get('blog-1'),
                $ids->get('blog-2'),
                $ids->get('blog-3'),
            ],
            $resultIds
        );
    }

    public function testOrSearch(): void
    {
        $ids = self::$indexedIds;

        $this->setSearchConfiguration(false, ['name']);
        $this->setSearchScores([]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('Aerodynamic Leather');
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->blogRepository->searchIds($criteria, Context::createDefaultContext());

        $resultIds = $result->getIds();

        static::assertCount(4, $resultIds, 'But got ' . $ids->getKeys($resultIds));

        static::assertSame(
            [
                $ids->get('blog-1'),
                $ids->get('blog-2'),
                $ids->get('blog-3'),
                $ids->get('blog-4'),
            ],
            $resultIds
        );
    }

    /**
     * @param list<string> $config
     * @param list<string> $expectedBlogs
     */
    #[DataProvider('providerSearchCases')]
    public function testSearch(array $config, string $term, array $expectedBlogs): void
    {
        $ids = self::$indexedIds;

        $this->registerCustomFieldsMapping();
        $this->setSearchConfiguration(false, $config);
        $this->setSearchScores([]);

        // Reduce the possible blogs to only those, which are set up in this test class. This makes sure other tests do not interfere.
        $criteria = new Criteria(array_values($ids->all()));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm($term);
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->blogRepository->searchIds($criteria, Context::createDefaultContext());

        $resultIds = $result->getIds();

        static::assertCount(\count($expectedBlogs), $resultIds, \sprintf('Blog count mismatch, Got "%s"', $ids->getKeys($resultIds)));

        foreach ($expectedBlogs as $key => $expectedBlog) {
            static::assertSame(
                $ids->get($expectedBlog),
                $resultIds[$key],
                \sprintf('Expected blog %s at position %d to be there, but got "%s"', $expectedBlog, $key, (string) $ids->getKey($resultIds[$key]))
            );
        }
    }

    public function testSearchWithStopWord(): void
    {
        $ids = self::$indexedIds;

        $this->setSearchConfiguration(false, ['name', 'description']);
        $this->setSearchScores([]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('the');
        $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));

        $result = $this->blogRepository->searchIds($criteria, Context::createDefaultContext());

        $resultIds = $result->getIds();

        static::assertCount(0, $resultIds, 'Blog count mismatch, Got ' . $ids->getKeys($resultIds));
    }

    public function testScoring(): void
    {
        $ids = self::$indexedIds;

        $this->setSearchConfiguration(false, ['name', 'description', 'customSearchKeywords']);
        $this->setSearchScores(['name' => 0, 'description' => 0, 'customSearchKeywords' => 50]);

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $criteria->setTerm('Pokemon Raichu');

        $result = $this->blogRepository->searchIds($criteria, Context::createDefaultContext());

        static::assertCount(2, $result->getIds());

        static::assertSame(
            [
                $ids->get('blog-9'), // Has Raichu as customSearchKeywords and is ranked higher
                $ids->get('blog-8'), // Has Pokemon in description
            ],
            $result->getIds()
        );
    }

    /**
     * @return \Generator<string, array{list<string>, string, list<string>}>
     */
    public static function providerSearchCases(): \Generator
    {
        yield 'search inside description' => [
            ['name', 'description'],
            'fooo',
            ['blog-4'],
        ];

        yield 'search for tags' => [
            ['name', 'description', 'customSearchKeywords', 'tags.name'],
            'Smarthome',
            ['blog-5'],
        ];

        yield 'search for customSearchKeywords' => [
            ['name', 'description', 'customSearchKeywords'],
            'Blueberry Activity',
            ['blog-3'],
        ];

        yield 'search for categories' => [
            ['name', 'description', 'customSearchKeywords', 'categories.name'],
            'Shoes',
            ['blog-1'],
        ];

        yield 'search joined technical terms in name' => [
            ['name'],
            'Channel Line',
            ['blog-13'],
        ];

        yield 'search technical terms in customSearchKeywords' => [
            ['customSearchKeywords'],
            'Channel Line',
            ['blog-14'],
        ];

        yield 'search for custom field json' => [
            ['customFields.evolvesTo'],
            'Flareon',
            ['blog-10'],
        ];

        yield 'search for custom field text' => [
            ['customFields.evolvesText'],
            'Jolteon',
            ['blog-11'],
        ];
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    private function buildIndex(): IdsCollection
    {
        $this->connection->executeStatement('DELETE FROM blog');

        $this->clearElasticsearch();
        $this->registerCustomFieldsMapping();

        $ids = new IdsCollection();
        $this->createData($ids);
        $this->indexElasticSearch();

        return $ids;
    }

    private function createData(IdsCollection $ids): void
    {
        $blogs = [
            new BlogBuilder($ids, 'blog-1')
                ->name('Aerodynamic Leather DotCondom')
                ->category('Shoes')
                ->visibility()
                ->build(),
            new BlogBuilder($ids, 'blog-2')
                ->name('Aerodynamic Leather Portaline')
                ->visibility()
                ->build(),
            new BlogBuilder($ids, 'blog-3')
                ->name('Aerodynamic Leather Wordlobster')
                ->add('customSearchKeywords', ['Activity'])
                ->visibility()
                ->build(),
            new BlogBuilder($ids, 'blog-4')
                ->name('Leather Red')
                ->description('Aerodynamic Fooo')
                ->visibility()
                ->build(),
            new BlogBuilder($ids, 'blog-5')
                ->name('Cycle Suave')
                ->tag('Smarthome')
                ->visibility()
                ->build(),
            new BlogBuilder($ids, 'blog-8')
                ->name('Super cool Pikachu Pokemon')
                ->description('A cool pokemon is traveling around the world')
                ->visibility()
                ->build(),
            new BlogBuilder($ids, 'blog-9')
                ->name('Super Pokemon')
                ->description('A cool raichu is traveling around the world')
                ->add('customSearchKeywords', ['Raichu'])
                ->visibility()
                ->build(),
            new BlogBuilder($ids, 'blog-13')
                ->name('ChannelLine Connector')
                ->visibility()
                ->build(),
            new BlogBuilder($ids, 'blog-14')
                ->name('Technical keyword accessory')
                ->add('customSearchKeywords', ['ChannelLine'])
                ->visibility()
                ->build(),
            new BlogBuilder($ids, 'blog-10')
                ->name('Eevee')
                ->customField('evolvesTo', ['Vaporeon', 'Jolteon', 'Flareon'])
                ->visibility()
                ->build(),
            new BlogBuilder($ids, 'blog-11')
                ->name('EeveeCfText')
                ->customField('evolvesText', 'Jolteon')
                ->visibility()
                ->build(),
        ];

        $this->blogRepository->create($blogs, Context::createDefaultContext());
    }

    private function registerCustomFieldsMapping(): void
    {
        $eventDispatcher = static::getContainer()->get('event_dispatcher');

        $this->addEventListener($eventDispatcher, ElasticsearchCustomFieldsMappingEvent::class, static function (ElasticsearchCustomFieldsMappingEvent $event): void {
            $event->setMapping('evolvesTo', CustomFieldTypes::SELECT);
            $event->setMapping('evolvesText', CustomFieldTypes::TEXT);
        });

        $definition = static::getContainer()->get(ElasticsearchIndexingUtils::class);

        $class = new \ReflectionClass($definition);
        $class->getProperty('customFieldsTypes')->setValue($definition, []);

        $service = new \ReflectionClass($this->customFieldService);
        $service->getProperty('customFields')->setValue($this->customFieldService, [
            'evolvesTo' => CustomFieldTypes::SELECT,
            'evolvesText' => CustomFieldTypes::TEXT,
        ]);
    }
}
