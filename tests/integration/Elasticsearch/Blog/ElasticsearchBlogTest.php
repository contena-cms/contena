<?php declare(strict_types=1);

namespace Contena\Tests\Integration\Elasticsearch\Blog;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Test\Blog\BlogBuilder;
use Contena\Core\Defaults;
use Contena\Core\Framework\Api\Context\SystemSource;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Aggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\DateHistogramAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\FilterAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\AvgAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\CountAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MaxAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\MinAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\RangeAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\SumAggregation;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\Bucket;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\BucketResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\DateHistogramResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\AvgResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\CountResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MinResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\RangeResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\StatsResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\SumResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\PrefixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\SuffixFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\CountSorting;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Test\TestCaseBase\ChannelApiTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Contena\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Contena\Core\Framework\Test\TestCaseBase\TenantTestBehaviour;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\CustomField\CustomFieldTypes;
use Contena\Core\System\Language\ChannelLanguageLoader;
use Contena\Core\Test\Stub\Framework\IdsCollection;
use Contena\Core\Test\TestDefaults;
use Contena\Elasticsearch\Blog\ElasticsearchBlogDefinition;
use Contena\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Contena\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Contena\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Contena\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class ElasticsearchBlogTest extends TestCase
{
    use ChannelApiTestBehaviour;
    use ElasticsearchTestTestBehaviour;
    use KernelTestBehaviour;
    use TenantTestBehaviour;

    private BlogDefinition $blogDefinition;

    /**
     * @var EntityRepository<BlogCollection>
     */
    private EntityRepository $blogRepository;

    private Context $context;

    private Connection $connection;

    private IdsCollection $ids;

    private static IdsCollection $indexedIds;

    /**
     * @var list<string>
     */
    private static array $tenantScopeChannelIds = [];

    /**
     * @var list<string>
     */
    private static array $tenantScopeTenantIds = [];

    protected function setUp(): void
    {
        $this->blogDefinition = static::getContainer()->get(BlogDefinition::class);
        $this->blogRepository = static::getContainer()->get('blog.repository');
        $this->context = Context::createDefaultContext();
        $this->connection = static::getContainer()->get(Connection::class);
        $this->ids = new IdsCollection();

        parent::setUp();

        if (!isset(self::$indexedIds)) {
            self::$indexedIds = $this->buildIndex();
        }
    }

    #[AfterClass]
    public static function cleanup(): void
    {
        $connection = KernelLifecycleManager::getKernel()->getContainer()->get(Connection::class);
        $connection->executeStatement('DELETE FROM blog');
        $connection->executeStatement('DELETE FROM custom_field WHERE name LIKE :name', ['name' => 'es\_blog\_%']);
        $connection->executeStatement('DELETE FROM custom_field_set WHERE name = :name', ['name' => 'elasticsearch_blog_test']);
        $connection->executeStatement('DELETE FROM elasticsearch_index_task');

        if (self::$tenantScopeChannelIds !== []) {
            $connection->executeStatement(
                'DELETE FROM channel WHERE id IN (:ids)',
                ['ids' => array_map(Uuid::fromHexToBytes(...), self::$tenantScopeChannelIds)],
                ['ids' => ArrayParameterType::BINARY],
            );
        }

        if (self::$tenantScopeTenantIds !== []) {
            $tenantIds = array_map(Uuid::fromHexToBytes(...), self::$tenantScopeTenantIds);
            $connection->executeStatement(
                'DELETE FROM blog_keyword_dictionary WHERE tenant_id IN (:ids)',
                ['ids' => $tenantIds],
                ['ids' => ArrayParameterType::BINARY],
            );
            $connection->executeStatement(
                'DELETE FROM tenant WHERE id IN (:ids)',
                ['ids' => $tenantIds],
                ['ids' => ArrayParameterType::BINARY],
            );
        }

        if (isset(self::$indexedIds) && self::$indexedIds->has('language-zh')) {
            KernelLifecycleManager::getKernel()->getContainer()->get('language.repository')->delete([
                ['id' => self::$indexedIds->get('language-zh')],
            ], Context::createDefaultContext());
        }
    }

    public function testUpdate(): void
    {
        $ids = self::$indexedIds;
        $this->ids = $ids;

        $this->blogRepository->upsert([
            new BlogBuilder($ids, 'update-blog')
                ->name('Update Blog')
                ->visibility()
                ->customField('es_blog_metric', 80)
                ->build(),
        ], $this->context);

        $criteria = new Criteria([$ids->get('update-blog')]);
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher = $this->createEntitySearcher();
        static::assertCount(1, $searcher->search($this->blogDefinition, $criteria, $this->context)->getIds());

        $this->blogRepository->delete([['id' => $ids->get('update-blog')]], $this->context);

        static::assertCount(0, $searcher->search($this->blogDefinition, $criteria, $this->context)->getIds());
    }

    public function testEmptySearch(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertCount(7, $result->getIds());
    }

    public function testPagination(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setLimit(1);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertCount(1, $result->getIds());
        static::assertSame(7, $result->getTotal());
    }

    public function testEqualsFilter(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.es_blog_metric', 20));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-2')));
    }

    public function testEqualsFilterWithNumericEncodedBoolFields(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('active', 1));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(5, $result->getTotal());
    }

    public function testRangeFilter(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new RangeFilter('customFields.es_blog_metric', [RangeFilter::GTE => 40]));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(4, $result->getTotal());
    }

    public function testEqualsAnyFilter(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsAnyFilter('blog.categoriesRo.id', [$ids->get('category-1')]));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(3, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-1')));
    }

    public function testMultiNotFilterFilter(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new RangeFilter('customFields.es_blog_metric', [RangeFilter::LTE => 20]),
            new ContainsFilter('blog.name', 'Alpha'),
        ]));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(6, $result->getTotal());
        static::assertFalse($result->has($ids->get('blog-1')));
    }

    public function testContainsFilter(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new ContainsFilter('blog.name', 'lph'));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-1')));
    }

    public function testPrefixFilter(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new PrefixFilter('blog.name', 'Gam'));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-3')));
    }

    public function testSuffixFilter(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new SuffixFilter('blog.name', 'tory'));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-4')));
    }

    public function testSingleGroupBy(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addGroupField(new FieldGrouping('type'));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertCount(2, $result->getIds());
    }

    public function testMultiGroupBy(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addGroupField(new FieldGrouping('type'));
        $criteria->addGroupField(new FieldGrouping('autoIncrement'));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertCount(5, $result->getIds());
    }

    public function testAvgAggregation(): void
    {
        $result = $this->aggregate(new AvgAggregation('avg-metric', 'blog.customFields.es_blog_metric'));

        static::assertTrue($result->has('avg-metric'));
        $metric = $result->get('avg-metric');
        static::assertInstanceOf(AvgResult::class, $metric);
        static::assertSame(40.0, $metric->getAvg());
    }

    public function testTermsAggregation(): void
    {
        $result = $this->aggregate(new TermsAggregation('types', 'blog.type'));

        $types = $result->get('types');
        static::assertInstanceOf(TermsResult::class, $types);
        static::assertSame(5, $types->get(BlogDefinition::TYPE_POST)?->getCount());
        static::assertSame(2, $types->get(BlogDefinition::TYPE_MEDIA)?->getCount());
    }

    public function testTermsAggregationWithAvg(): void
    {
        $result = $this->aggregate(new TermsAggregation(
            'types',
            'blog.type',
            null,
            null,
            new AvgAggregation('avg-metric', 'blog.customFields.es_blog_metric')
        ));

        $types = $result->get('types');
        static::assertInstanceOf(TermsResult::class, $types);
        $postAverage = $types->get(BlogDefinition::TYPE_POST)?->getResult();
        static::assertInstanceOf(AvgResult::class, $postAverage);
        static::assertSame(42.0, $postAverage->getAvg());
        $mediaAverage = $types->get(BlogDefinition::TYPE_MEDIA)?->getResult();
        static::assertInstanceOf(AvgResult::class, $mediaAverage);
        static::assertSame(35.0, $mediaAverage->getAvg());
    }

    public function testSumAggregation(): void
    {
        $result = $this->aggregate(new SumAggregation('sum-metric', 'blog.customFields.es_blog_metric'));

        $metric = $result->get('sum-metric');
        static::assertInstanceOf(SumResult::class, $metric);
        static::assertSame(280.0, $metric->getSum());
    }

    public function testMaxAggregation(): void
    {
        $result = $this->aggregate(new MaxAggregation('max-metric', 'blog.customFields.es_blog_metric'));

        $metric = $result->get('max-metric');
        static::assertInstanceOf(MaxResult::class, $metric);
        static::assertSame(70.0, $metric->getMax());
    }

    public function testMinAggregation(): void
    {
        $result = $this->aggregate(new MinAggregation('min-metric', 'blog.customFields.es_blog_metric'));

        $metric = $result->get('min-metric');
        static::assertInstanceOf(MinResult::class, $metric);
        static::assertSame(10.0, $metric->getMin());
    }

    public function testCountAggregation(): void
    {
        $result = $this->aggregate(new CountAggregation('count-metric', 'blog.customFields.es_blog_metric'));

        $metric = $result->get('count-metric');
        static::assertInstanceOf(CountResult::class, $metric);
        static::assertSame(7, $metric->getCount());
    }

    public function testStatsAggregation(): void
    {
        $result = $this->aggregate(new StatsAggregation('metric-stats', 'blog.customFields.es_blog_metric'));

        $metric = $result->get('metric-stats');
        static::assertInstanceOf(StatsResult::class, $metric);
        static::assertSame(10.0, $metric->getMin());
        static::assertSame(70.0, $metric->getMax());
        static::assertSame(40.0, $metric->getAvg());
        static::assertSame(280.0, $metric->getSum());
    }

    public function testEntityAggregation(): void
    {
        $ids = self::$indexedIds;
        $result = $this->aggregate(new EntityAggregation('categories', 'blog.categoryIds', CategoryDefinition::ENTITY_NAME));

        $categories = $result->get('categories');
        static::assertInstanceOf(EntityResult::class, $categories);
        static::assertCount(2, $categories->getEntities());
        static::assertTrue($categories->getEntities()->has($ids->get('category-1')));
        static::assertTrue($categories->getEntities()->has($ids->get('category-2')));
    }

    public function testEntityAggregationWithTermQuery(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTerm('Alpha');
        $criteria->addAggregation(new EntityAggregation('categories', 'blog.categoryIds', CategoryDefinition::ENTITY_NAME));

        $aggregations = $this->createEntityAggregator()->aggregate($this->blogDefinition, $criteria, $this->context);
        $categories = $aggregations->get('categories');

        static::assertInstanceOf(EntityResult::class, $categories);
        static::assertCount(1, $categories->getEntities());
        static::assertTrue($categories->getEntities()->has($ids->get('category-1')));
    }

    public function testFilterAggregation(): void
    {
        $ids = self::$indexedIds;
        $result = $this->aggregate(new FilterAggregation(
            'filter',
            new AvgAggregation('avg-metric', 'blog.customFields.es_blog_metric'),
            [new EqualsAnyFilter('blog.id', $ids->getList(['blog-1', 'blog-2']))]
        ));

        $metric = $result->get('avg-metric');
        static::assertInstanceOf(AvgResult::class, $metric);
        static::assertSame(15.0, $metric->getAvg());
    }

    public function testFilterAggregationWithNestedFilterAndAggregation(): void
    {
        $ids = self::$indexedIds;
        $result = $this->aggregate(new FilterAggregation(
            'tags-filtered',
            new TermsAggregation('tags', 'blog.tags.id'),
            [new EqualsAnyFilter('blog.tags.id', [$ids->get('tag-1')])]
        ));

        $tags = $result->get('tags');
        static::assertInstanceOf(TermsResult::class, $tags);
        static::assertSame([$ids->get('tag-1')], $tags->getKeys());
    }

    public function testNestedFilterAggregationWithRootQuery(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->getList(['blog-1', 'blog-2']));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsAnyFilter('id', $ids->getList(['blog-1', 'blog-2'])));
        $criteria->addAggregation(new FilterAggregation(
            'tags-filtered',
            new TermsAggregation('tags', 'blog.tags.id'),
            [new EqualsFilter('blog.type', BlogDefinition::TYPE_POST)]
        ));

        $aggregations = $this->createEntityAggregator()->aggregate($this->blogDefinition, $criteria, $this->context);

        $result = $aggregations->get('tags');
        static::assertInstanceOf(BucketResult::class, $result);
        static::assertContains($ids->get('tag-1'), $result->getKeys());
        static::assertNotContains($ids->get('tag-2'), $result->getKeys());
    }

    public function testFilterAggregationWithRootFilter(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->getList(['blog-1', 'blog-2']));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsAnyFilter('id', $ids->getList(['blog-1', 'blog-2'])));
        $criteria->addAggregation(new FilterAggregation(
            'tags-filtered',
            new TermsAggregation('tags', 'blog.tags.id'),
            [new EqualsFilter('blog.type', BlogDefinition::TYPE_MEDIA)]
        ));

        $aggregations = $this->createEntityAggregator()->aggregate($this->blogDefinition, $criteria, $this->context);

        $result = $aggregations->get('tags');
        static::assertInstanceOf(BucketResult::class, $result);
        static::assertNotContains($ids->get('tag-1'), $result->getKeys());
        static::assertContains($ids->get('tag-2'), $result->getKeys());
    }

    public function testDateHistogramWithNestedAvg(): void
    {
        $result = $this->aggregate(new DateHistogramAggregation(
            'release-histogram',
            'releaseDate',
            DateHistogramAggregation::PER_MONTH,
            null,
            new AvgAggregation('metric', 'blog.customFields.es_blog_metric')
        ));

        $histogram = $result->get('release-histogram');
        static::assertInstanceOf(DateHistogramResult::class, $histogram);

        $bucket = $histogram->get('2019-01-01 00:00:00');
        static::assertInstanceOf(Bucket::class, $bucket);
        $metric = $bucket->getResult();
        static::assertInstanceOf(AvgResult::class, $metric);
        static::assertSame(15.0, $metric->getAvg());

        $bucket = $histogram->get('2021-12-01 00:00:00');
        static::assertInstanceOf(Bucket::class, $bucket);
        $metric = $bucket->getResult();
        static::assertInstanceOf(AvgResult::class, $metric);
        static::assertSame(55.0, $metric->getAvg());
    }

    public function testFilterCustomTextField(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.es_blog_text', 'alpha'));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-1')));
        static::assertTrue($result->has($ids->get('blog-3')));
    }

    public function testFilterCustomTextFieldEqualNull(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.es_blog_text', null));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(1, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-7')));
    }

    public function testXorQuery(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_XOR, [
            new EqualsFilter('type', BlogDefinition::TYPE_MEDIA),
            new EqualsFilter('active', false),
        ]));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(4, $result->getTotal());
    }

    public function testNegativeXorQuery(): void
    {
        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_XOR, [
            new EqualsFilter('type', 'unknown'),
            new EqualsFilter('id', '00000000000000000000000000000000'),
        ]));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(0, $result->getTotal());
    }

    public function testTotalWithGroupFieldAndPostFilter(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addGroupField(new FieldGrouping('autoIncrement'));
        $criteria->addPostFilter(new EqualsAnyFilter('blog.categoriesRo.id', [$ids->get('category-2')]));

        $blogs = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(3, $blogs->getTotal());
        static::assertCount(3, $blogs->getIds());
        static::assertTrue($blogs->has($ids->get('blog-4')));
        static::assertTrue($blogs->has($ids->get('blog-5')));
        static::assertTrue($blogs->has($ids->get('blog-6')));
    }

    public function testIdsSorting(): void
    {
        $ids = self::$indexedIds;
        $expected = $ids->getList(['blog-2', 'blog-3', 'blog-1', 'blog-4', 'blog-5']);
        $criteria = new Criteria($expected);
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new RangeFilter('customFields.es_blog_metric', [RangeFilter::GTE => 0]));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(array_values($expected), $result->getIds());
    }

    public function testSorting(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new FieldSorting('name'));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(array_values($ids->getList([
            'blog-1',
            'blog-2',
            'blog-4',
            'blog-5',
            'blog-3',
            'blog-7',
            'blog-6',
        ])), $result->getIds());
    }

    public function testMaxLimit(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('limit-blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $blogs = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertCount(11, $blogs->getIds());
    }

    public function testSortingIsCaseInsensitive(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('case-blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('categoriesRo.id', $ids->get('case-category')));
        $criteria->addSorting(new FieldSorting('name'));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context)->getIds();

        $idList = array_chunk($result, 3);
        static::assertContains($ids->get('case-blog-1'), $idList[0]);
        static::assertContains($ids->get('case-blog-2'), $idList[0]);
        static::assertContains($ids->get('case-blog-3'), $idList[0]);
        static::assertContains($ids->get('case-blog-4'), $idList[1]);
        static::assertContains($ids->get('case-blog-5'), $idList[1]);
        static::assertContains($ids->get('case-blog-6'), $idList[1]);
    }

    public function testSortByTagsCount(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('count-blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new CountSorting('tags.id', CountSorting::DESCENDING));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context)->getIds();

        static::assertSame($ids->get('count-blog-3'), $result[0]);
        static::assertSame($ids->get('count-blog-2'), $result[1]);
        static::assertSame($ids->get('count-blog-1'), $result[2]);

        $criteria = new Criteria($ids->prefixed('count-blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new CountSorting('tags.id', CountSorting::ASCENDING));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context)->getIds();

        static::assertSame($ids->get('count-blog-1'), $result[0]);
        static::assertSame($ids->get('count-blog-2'), $result[1]);
        static::assertSame($ids->get('count-blog-3'), $result[2]);
    }

    public function testNestedSorting(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('sort-blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new FieldSorting('tags.name'));

        $searcher = $this->createEntitySearcher();
        $result = $searcher->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame($ids->get('sort-blog-amazon'), $result->getIds()[0]);
        static::assertSame($ids->get('sort-blog-contena'), $result->getIds()[1]);
        static::assertSame($ids->get('sort-blog-zalando'), $result->getIds()[2]);

        $criteria = new Criteria($ids->prefixed('sort-blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new FieldSorting('tags.name', FieldSorting::DESCENDING));
        $result = $searcher->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame($ids->get('sort-blog-zalando'), $result->getIds()[0]);
        static::assertSame($ids->get('sort-blog-contena'), $result->getIds()[1]);
        static::assertSame($ids->get('sort-blog-amazon'), $result->getIds()[2]);
    }

    public function testRangeAggregation(): void
    {
        $result = $this->aggregate(new RangeAggregation('metric-ranges', 'blog.customFields.es_blog_metric', [
            ['to' => 30],
            ['from' => 30, 'to' => 60],
            ['from' => 60],
        ]));

        $ranges = $result->get('metric-ranges');
        static::assertInstanceOf(RangeResult::class, $ranges);
        static::assertSame([
            '*-30' => 2,
            '30-60' => 3,
            '60-*' => 2,
        ], $ranges->getRanges());
    }

    public function testReleaseDate(): void
    {
        $ids = self::$indexedIds;
        $documents = static::getContainer()->get(ElasticsearchBlogDefinition::class)
            ->fetch([$ids->getBytes('blog-1')], $this->context);

        static::assertSame('2019-01-01T10:11:00+00:00', $documents[$ids->get('blog-1')]['releaseDate']);
    }

    public function testWritesAndReadsRespectTenantContexts(): void
    {
        $tenantA = $this->createTenant('Elasticsearch Blog tenant A');
        $tenantB = $this->createTenant('Elasticsearch Blog tenant B');
        self::$tenantScopeTenantIds = [$tenantA->id, $tenantB->id];
        $contexts = [
            'platform' => Context::createDefaultContext(),
            'tenant-a' => $this->createTenantContext($tenantA),
            'tenant-b' => $this->createTenantContext($tenantB),
            'global' => Context::createGlobalContext(),
        ];
        $channelIds = [
            'tenant-a' => Uuid::randomHex(),
            'tenant-b' => Uuid::randomHex(),
        ];
        self::$tenantScopeChannelIds = array_values($channelIds);
        $blogIds = [
            'platform' => Uuid::randomHex(),
            'tenant-a' => Uuid::randomHex(),
            'tenant-b' => Uuid::randomHex(),
            'global' => Uuid::randomHex(),
        ];

        foreach (['tenant-a', 'tenant-b'] as $tenantScope) {
            $this->createChannel([
                'id' => $channelIds[$tenantScope],
                'domains' => [[
                    'languageId' => Defaults::LANGUAGE_SYSTEM,
                    'snippetSetId' => $this->getSnippetSetIdForLocale('en-GB'),
                    'url' => 'https://' . $tenantScope . '-es-blog.test',
                ]],
            ], $contexts[$tenantScope]);
        }

        foreach ($contexts as $scope => $context) {
            $channelId = $channelIds[$scope] ?? TestDefaults::CHANNEL;
            $this->blogRepository->create([[
                'id' => $blogIds[$scope],
                'name' => ucfirst($scope) . ' Elasticsearch Blog',
                'active' => true,
                'type' => BlogDefinition::TYPE_POST,
                'visibilities' => [[
                    'channelId' => $channelId,
                    'visibility' => 30,
                ]],
            ]], $context);
        }

        $definition = static::getContainer()->get(ElasticsearchBlogDefinition::class);
        $bytes = array_map(Uuid::fromHexToBytes(...), array_values($blogIds));
        $expectedIds = [
            'platform' => [$blogIds['global'], $blogIds['platform']],
            'tenant-a' => [$blogIds['tenant-a']],
            'tenant-b' => [$blogIds['tenant-b']],
            'global' => array_values($blogIds),
        ];

        foreach ($contexts as $scope => $context) {
            $documents = $definition->fetch($bytes, $context);
            $actualIds = array_keys($documents);
            sort($actualIds);
            sort($expectedIds[$scope]);

            static::assertSame($expectedIds[$scope], $actualIds, 'Unexpected database documents for ' . $scope);
        }

        $globalDocuments = $definition->fetch($bytes, $contexts['global']);
        static::assertNull($globalDocuments[$blogIds['platform']]['tenantId']);
        static::assertSame($tenantA->id, $globalDocuments[$blogIds['tenant-a']]['tenantId']);
        static::assertSame($tenantB->id, $globalDocuments[$blogIds['tenant-b']]['tenantId']);
        static::assertNull($globalDocuments[$blogIds['global']]['tenantId']);

        $indexer = static::getContainer()->get(ElasticsearchIndexer::class);
        foreach ($contexts as $scope => $context) {
            $indexer->updateIds($this->blogDefinition, [$blogIds[$scope]], $context);
        }

        $searcher = $this->createEntitySearcher();
        foreach ($contexts as $scope => $context) {
            $criteria = new Criteria(array_values($blogIds));
            $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
            $actualIds = $searcher->search($this->blogDefinition, $criteria, $context)->getIds();
            sort($actualIds);

            static::assertSame($expectedIds[$scope], $actualIds, 'Unexpected Elasticsearch documents for ' . $scope);
        }
    }

    public function testFilterCoreDateFields(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new RangeFilter('releaseDate', [RangeFilter::GTE => '2021-01-01 00:00:00']));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);

        static::assertSame(3, $result->getTotal());
        static::assertTrue($result->has($ids->get('blog-5')));
        static::assertTrue($result->has($ids->get('blog-6')));
        static::assertTrue($result->has($ids->get('blog-7')));
    }

    public function testDateHistogram(): void
    {
        $result = $this->aggregate(new DateHistogramAggregation(
            'release-histogram',
            'releaseDate',
            DateHistogramAggregation::PER_MONTH
        ));

        $histogram = $result->get('release-histogram');
        static::assertInstanceOf(DateHistogramResult::class, $histogram);
        static::assertSame(2, $histogram->get('2019-01-01 00:00:00')?->getCount());
        static::assertSame(1, $histogram->get('2019-06-01 00:00:00')?->getCount());
        static::assertSame(1, $histogram->get('2020-09-01 00:00:00')?->getCount());
        static::assertSame(2, $histogram->get('2021-12-01 00:00:00')?->getCount());
        static::assertSame(1, $histogram->get('2024-12-01 00:00:00')?->getCount());
    }

    public function testCustomFieldDateType(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addSorting(new FieldSorting('customFields.es_blog_date', FieldSorting::DESCENDING));

        $result = $this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context);
        static::assertSame($ids->get('blog-7'), $result->getIds()[0]);

        $criteria = new Criteria($ids->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('customFields.es_blog_date', '2024-12-11 00:00:00.000'));

        static::assertTrue($this->createEntitySearcher()->search($this->blogDefinition, $criteria, $this->context)->has($ids->get('blog-7')));
    }

    public function testLanguageFieldsWorkSimilarToDal(): void
    {
        $ids = self::$indexedIds;
        $languageId = $ids->get('language-zh');
        $languageContext = new Context(new SystemSource(), [$languageId, Defaults::LANGUAGE_SYSTEM]);
        $definition = static::getContainer()->get(ElasticsearchBlogDefinition::class);

        $documents = $definition->fetch([$ids->getBytes('blog-1')], $languageContext);
        $document = $documents[$ids->get('blog-1')];
        $blog = $this->blogRepository->search(new Criteria([$ids->get('blog-1')]), $languageContext)->getEntities()->first();

        static::assertNotNull($blog);
        static::assertSame($blog->getTranslation('name'), $document['name'][$languageId]);
        static::assertSame($blog->getTranslation('description'), $document['description'][$languageId]);
        $customFields = $blog->getTranslation('customFields');
        static::assertIsArray($customFields);
        static::assertSame($customFields['es_blog_text'], $document['customFields'][Defaults::LANGUAGE_SYSTEM]['es_blog_text']);

        $documents = $definition->fetch([$ids->getBytes('blog-2')], $languageContext);
        $document = $documents[$ids->get('blog-2')];
        $blog = $this->blogRepository->search(new Criteria([$ids->get('blog-2')]), $languageContext)->getEntities()->first();

        static::assertNotNull($blog);
        static::assertSame($blog->getTranslation('name'), $document['name'][$languageId]);
        static::assertSame($blog->getTranslation('description'), $document['description'][$languageId]);
        $customFields = $blog->getTranslation('customFields');
        static::assertIsArray($customFields);
        static::assertSame($customFields['es_blog_text'], $document['customFields'][Defaults::LANGUAGE_SYSTEM]['es_blog_text']);
    }

    public function testEmptyEntityAggregation(): void
    {
        $ids = self::$indexedIds;
        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addFilter(new EqualsFilter('id', $ids->get('blog-7')));
        $criteria->addAggregation(new EntityAggregation('categories', 'blog.categoryIds', CategoryDefinition::ENTITY_NAME));

        $aggregations = $this->createEntityAggregator()->aggregate($this->blogDefinition, $criteria, $this->context);
        $categories = $aggregations->get('categories');

        static::assertInstanceOf(EntityResult::class, $categories);
        static::assertEmpty($categories->getEntities());
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }

    protected function runWorker(): void
    {
    }

    private function aggregate(Aggregation $aggregation): AggregationResultCollection
    {
        $criteria = new Criteria(self::$indexedIds->prefixed('blog-'));
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->addAggregation($aggregation);

        return $this->createEntityAggregator()->aggregate($this->blogDefinition, $criteria, $this->context);
    }

    private function buildIndex(): IdsCollection
    {
        $this->connection->executeStatement('DELETE FROM blog');
        $this->connection->executeStatement('DELETE FROM custom_field WHERE name LIKE :name', ['name' => 'es\_blog\_%']);
        $this->connection->executeStatement('DELETE FROM custom_field_set WHERE name = :name', ['name' => 'elasticsearch_blog_test']);
        $this->clearElasticsearch();

        $this->createChineseLanguage();
        $this->createCustomFields();
        $this->createBlogs();

        $utils = static::getContainer()->get(ElasticsearchIndexingUtils::class);
        new \ReflectionProperty(ElasticsearchIndexingUtils::class, 'customFieldsTypes')->setValue($utils, []);

        $command = new ElasticsearchIndexingCommand(
            static::getContainer()->get(ElasticsearchIndexer::class),
            static::getContainer()->get('messenger.default_bus'),
            static::getContainer()->get(CreateAliasTaskHandler::class),
            true
        );
        $command->run(new ArrayInput([]), new NullOutput());

        static::getContainer()->get(ElasticsearchIndexer::class)->updateIds(
            $this->blogDefinition,
            array_values([
                ...$this->ids->prefixed('blog-'),
                ...$this->ids->prefixed('limit-blog-'),
                ...$this->ids->prefixed('case-blog-'),
                ...$this->ids->prefixed('count-blog-'),
                ...$this->ids->prefixed('sort-blog-'),
            ]),
            $this->context,
        );

        return $this->ids;
    }

    private function createCustomFields(): void
    {
        static::getContainer()->get('custom_field_set.repository')->create([[
            'id' => $this->ids->get('custom-field-set'),
            'name' => 'elasticsearch_blog_test',
            'config' => ['label' => ['en-GB' => 'Elasticsearch Blog Test']],
            'relations' => [['entityName' => BlogDefinition::ENTITY_NAME]],
            'customFields' => [
                [
                    'name' => 'es_blog_metric',
                    'type' => CustomFieldTypes::INT,
                    'includeInSearch' => true,
                ],
                [
                    'name' => 'es_blog_text',
                    'type' => CustomFieldTypes::TEXT,
                    'includeInSearch' => true,
                ],
                [
                    'name' => 'es_blog_group',
                    'type' => CustomFieldTypes::TEXT,
                    'includeInSearch' => true,
                ],
                [
                    'name' => 'es_blog_date',
                    'type' => CustomFieldTypes::DATETIME,
                    'includeInSearch' => true,
                ],
            ],
        ]], $this->context);
    }

    private function createBlogs(): void
    {
        $this->ids->get('tag-1');
        $this->ids->get('tag-2');

        $blogs = [
            $this->blog('blog-1', 'Alpha Guide', 10)->releaseDate('2019-01-01 10:11:00')->category('category-1')->tag('tag-1')->customField('es_blog_text', 'alpha')->customField('es_blog_group', 'group-a')->customField('es_blog_date', '2019-01-01 00:00:00.000')->translation($this->ids->get('language-zh'), 'name', '中文指南')->translation($this->ids->get('language-zh'), 'description', '中文内容')->build(),
            $this->blog('blog-2', 'Beta Media', 20)->releaseDate('2019-01-01 10:13:00')->type(BlogDefinition::TYPE_MEDIA)->category('category-1')->tag('tag-2')->customField('es_blog_text', 'beta')->customField('es_blog_group', 'group-a')->customField('es_blog_date', '2019-01-01 00:00:00.000')->build(),
            $this->blog('blog-3', 'Gamma Notes', 30)->releaseDate('2019-06-15 13:00:00')->active(false)->category('category-1')->tag('tag-1')->customField('es_blog_text', 'alpha')->customField('es_blog_group', 'group-a')->customField('es_blog_date', '2019-06-15 00:00:00.000')->build(),
            $this->blog('blog-4', 'Delta Story', 40)->releaseDate('2020-09-30 15:00:00')->category('category-2')->tag('tag-2')->customField('es_blog_text', 'delta')->customField('es_blog_group', 'group-b')->customField('es_blog_date', '2020-09-30 00:00:00.000')->build(),
            $this->blog('blog-5', 'Epsilon Media', 50)->releaseDate('2021-12-10 11:59:00')->type(BlogDefinition::TYPE_MEDIA)->category('category-2')->tag('tag-1')->customField('es_blog_text', 'epsilon')->customField('es_blog_group', 'group-b')->customField('es_blog_date', '2021-12-10 00:00:00.000')->build(),
            $this->blog('blog-6', 'Zeta Reference', 60)->releaseDate('2021-12-10 11:59:00')->active(false)->category('category-2')->tag('tag-2')->customField('es_blog_text', 'zeta')->customField('es_blog_group', 'group-b')->customField('es_blog_date', '2021-12-10 00:00:00.000')->build(),
            $this->blog('blog-7', 'Omega Overview', 70)->releaseDate('2024-12-11 23:59:00')->tag('tag-1')->customField('es_blog_group', 'group-b')->customField('es_blog_date', '2024-12-11 00:00:00.000')->build(),
        ];

        for ($index = 1; $index <= 11; ++$index) {
            $blogs[] = $this->blog('limit-blog-' . $index, 'Limit Blog ' . $index, $index)->build();
        }

        foreach (['Aa', 'AA', 'aa', 'Bb', 'BB', 'bb'] as $index => $name) {
            $blogs[] = $this->blog('case-blog-' . ($index + 1), $name, $index + 1)->category('case-category')->build();
        }

        $blogs[] = $this->blog('count-blog-1', 'Count One', 81)->tag('count-tag-1')->build();
        $blogs[] = $this->blog('count-blog-2', 'Count Two', 82)->tag('count-tag-1')->tag('count-tag-2')->build();
        $blogs[] = $this->blog('count-blog-3', 'Count Three', 83)->tag('count-tag-1')->tag('count-tag-2')->tag('count-tag-3')->build();
        $blogs[] = $this->blog('sort-blog-contena', 'contena', 91)->tag('contena')->build();
        $blogs[] = $this->blog('sort-blog-amazon', 'Amazon', 92)->tag('amazon')->build();
        $blogs[] = $this->blog('sort-blog-zalando', 'Zalando', 93)->tag('zalando')->build();

        $this->blogRepository->create($blogs, $this->context);
    }

    private function blog(string $key, string $name, int $metric): BlogBuilder
    {
        return new BlogBuilder($this->ids, $key)
            ->name($name)
            ->visibility(TestDefaults::CHANNEL)
            ->customField('es_blog_metric', $metric);
    }

    private function createChineseLanguage(): void
    {
        $localeId = static::getContainer()->get('locale.repository')->searchIds(
            new Criteria()->addFilter(new EqualsFilter('code', Defaults::DEFAULT_LOCALE)),
            $this->context
        )->firstId();
        static::assertNotNull($localeId);

        static::getContainer()->get('language.repository')->create([[
            'id' => $this->ids->get('language-zh'),
            'name' => 'Chinese Elasticsearch Test',
            'localeId' => $localeId,
            'parentId' => Defaults::LANGUAGE_SYSTEM,
            'active' => true,
            'channels' => [['id' => TestDefaults::CHANNEL]],
        ]], $this->context);

        static::getContainer()->get(ChannelLanguageLoader::class)->reset();
    }
}
