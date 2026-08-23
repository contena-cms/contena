<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Flow\FlowCollection;
use Contena\Core\Content\Flow\FlowDefinition;
use Contena\Core\Content\Flow\FlowEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Contena\Elasticsearch\Admin\AdminElasticsearchHelper;
use Contena\Elasticsearch\Admin\AdminSearcher;
use Contena\Elasticsearch\Admin\AdminSearchRegistry;
use Contena\Elasticsearch\Admin\Indexer\AbstractAdminIndexer;
use Contena\Elasticsearch\Admin\Indexer\BlogAdminSearchIndexer;
use Contena\Elasticsearch\ElasticsearchException;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchSearchHydrator;
use Contena\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Contena\Elasticsearch\Framework\ElasticsearchHelper;

/**
 * @internal
 */
#[CoversClass(AdminSearcher::class)]
class AdminSearcherTest extends TestCase
{
    private Client&MockObject $client;

    private AdminSearcher $searcher;

    private AdminSearchRegistry&Stub $registry;

    private AbstractAdminIndexer $blogIndexer;

    protected function setUp(): void
    {
        $this->client = $this->createMock(Client::class);

        $this->registry = static::createStub(AdminSearchRegistry::class);

        $this->blogIndexer = new BlogAdminSearchIndexer(
            static::createStub(Connection::class),
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            static::createStub(ElasticsearchFieldBuilder::class),
            100
        );
        $this->registry->method('getIndexers')->willReturn(['blog' => $this->blogIndexer]);
        $this->registry->method('hasIndexer')->willReturn(true);
        $this->registry->method('getIndexer')->willReturn($this->blogIndexer);

        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $this->searcher = new AdminSearcher(
            $this->client,
            $this->registry,
            $searchHelper,
            $this->getDefinitionRegistry(),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            static::createStub(ElasticsearchHelper::class),
            '5s',
            20,
            'query_then_fetch',
        );
    }

    public function testElasticSearch(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('elasticsearch*'))
            ->willReturn($this->getMockResponse('c1a28776116d4431a2208eb2960ec340 elasticsearch'));

        $data = $this->searcher->search('elasticsearch', ['blog'], Context::createDefaultContext());

        $this->assertSearchResult($data, 1, 'blog-listing', 'ct-admin-blog-listing');
    }

    public function testSearchWithLimit(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('elast*', '1s'))
            ->willReturn($this->getMockResponse('c1a28776116d4431a2208eb2960ec340 elasticsearch'));

        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger());
        $searcher = new AdminSearcher(
            $this->client,
            $this->registry,
            $searchHelper,
            $this->getDefinitionRegistry(),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            static::createStub(ElasticsearchHelper::class),
            '1s',
            5,
            'query_then_fetch',
        );

        $data = $searcher->search('elasticsearch', ['blog'], Context::createDefaultContext());

        $this->assertSearchResult($data, 1, 'blog-listing', 'ct-admin-blog-listing');
    }

    public function testTenantSearchFiltersTenantScopedDocuments(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('elasticsearch*', '5s', 'tenant-a'))
            ->willReturn($this->getMockResponse('c1a28776116d4431a2208eb2960ec340 elasticsearch'));

        $data = $this->searcher->search('elasticsearch', ['blog'], Context::createTenantContext('tenant-a'));

        $this->assertSearchResult($data, 1, 'blog-listing', 'ct-admin-blog-listing');
    }

    public function testAnotherTenantSearchUsesItsOwnTenantFilter(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('elasticsearch*', '5s', 'tenant-b'))
            ->willReturn($this->getMockResponse('c1a28776116d4431a2208eb2960ec340 elasticsearch'));

        $data = $this->searcher->search('elasticsearch', ['blog'], Context::createTenantContext('tenant-b'));

        $this->assertSearchResult($data, 1, 'blog-listing', 'ct-admin-blog-listing');
    }

    public function testGlobalSearchDoesNotFilterTenantScopedDocuments(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('elasticsearch*', '5s', platformOnly: false))
            ->willReturn($this->getMockResponse('c1a28776116d4431a2208eb2960ec340 elasticsearch'));

        $data = $this->searcher->search('elasticsearch', ['blog'], Context::createGlobalContext());

        $this->assertSearchResult($data, 1, 'blog-listing', 'ct-admin-blog-listing');
    }

    public function testSearchWithUndefinedIndexerAndUnknownEntity(): void
    {
        $registry = static::createStub(AdminSearchRegistry::class);
        $registry->method('hasIndexer')->willReturn(false);

        $this->client->expects($this->never())->method('msearch');

        $searcher = $this->createSearcher($registry, static::createStub(DefinitionInstanceRegistry::class));

        $data = $searcher->search('elasticsearch', ['test'], Context::createDefaultContext());

        static::assertEmpty($data);
    }

    public function testSearchFallsBackToTheDalWhenTheEntityHasNoIndexer(): void
    {
        $registry = static::createStub(AdminSearchRegistry::class);
        $registry->method('hasIndexer')->willReturn(false);

        $this->client->expects($this->never())->method('msearch');

        $flow = new FlowEntity();
        $flow->setUniqueIdentifier(Uuid::randomHex());

        $searchedCriteria = null;
        $repository = StaticEntityRepository::of(
            FlowCollection::class,
            [
                function (Criteria $criteria) use (&$searchedCriteria, $flow): FlowCollection {
                    $searchedCriteria = $criteria;

                    return new FlowCollection([$flow]);
                },
            ],
            new FlowDefinition()
        );

        $definitionRegistry = $this->getDefinitionRegistry();
        $definitionRegistry->method('has')->willReturn(true);
        $definitionRegistry->method('getRepository')->willReturn($repository);

        $searcher = $this->createSearcher($registry, $definitionRegistry);

        $data = $searcher->search('Order and placed', ['flow'], Context::createDefaultContext());

        static::assertSame(1, $data['flow']['total']);
        static::assertSame([$flow], array_values($data['flow']['data']->getElements()));

        static::assertInstanceOf(Criteria::class, $searchedCriteria);
        // raw term, not the elasticsearch operator syntax where "and" becomes "+"
        static::assertSame('Order and placed', $searchedCriteria->getTerm());
        static::assertSame(5, $searchedCriteria->getLimit());
    }

    public function testSearchFallsBackToTheDalWhenTheIndexerCannotBeResolved(): void
    {
        $registry = static::createStub(AdminSearchRegistry::class);
        $registry->method('hasIndexer')->willReturn(true);
        $registry->method('getIndexer')->willReturnCallback(function (string $entityName): AbstractAdminIndexer {
            if ($entityName === 'flow') {
                throw ElasticsearchException::indexingError(['Indexer for name flow not found']);
            }

            return $this->blogIndexer;
        });

        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('elasticsearch*'))
            ->willReturn($this->getMockResponse('c1a28776116d4431a2208eb2960ec340 elasticsearch'));

        $flow = new FlowEntity();
        $flow->setUniqueIdentifier(Uuid::randomHex());

        $definitionRegistry = $this->getDefinitionRegistry();
        $definitionRegistry->method('has')->willReturn(true);
        $definitionRegistry->method('getRepository')->willReturn(
            StaticEntityRepository::of(FlowCollection::class, [new FlowCollection([$flow])], new FlowDefinition())
        );

        $searcher = $this->createSearcher($registry, $definitionRegistry);

        $data = $searcher->search('elasticsearch', ['blog', 'flow'], Context::createDefaultContext());

        $this->assertSearchResult($data, 1, 'blog-listing', 'ct-admin-blog-listing');
        static::assertSame(1, $data['flow']['total']);
    }

    public function testSearchWithNumericTerm(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('3800*'))
            ->willReturn($this->getMockResponse('blog x3800'));

        $data = $this->searcher->search('3800', ['blog'], Context::createDefaultContext());

        $this->assertSearchResult($data, 1, 'blog-listing', 'ct-admin-blog-listing');
    }

    public function testSearchWithMixedTermContainingNumeric(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('blog 3800*'))
            ->willReturn($this->getMockResponse('blog 3800'));

        $data = $this->searcher->search('blog 3800', ['blog'], Context::createDefaultContext());

        static::assertNotEmpty($data['blog']);
        static::assertSame(1, $data['blog']['total']);
    }

    public function testSearchWithPureNumeric(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('123*'))
            ->willReturn($this->getMockResponse('blog 123'));

        $data = $this->searcher->search('123', ['blog'], Context::createDefaultContext());

        static::assertNotEmpty($data['blog']);
        static::assertSame(1, $data['blog']['total']);
    }

    public function testSearchNormalizesTermLevelQueries(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->with($this->getQueryBody('LAPTO*'))
            ->willReturn($this->getMockResponse('laptop computer'));

        $data = $this->searcher->search('LAPTO', ['blog'], Context::createDefaultContext());

        static::assertNotEmpty($data['blog']);
        static::assertSame(1, $data['blog']['total']);
    }

    public function testSearchReturnsEmptyResultWhenClientFailsAndExceptionsAreSuppressed(): void
    {
        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->willThrowException(new \RuntimeException('No alive nodes found in your cluster'));

        $searchHelper = new AdminElasticsearchHelper(true, false, 'ct-admin', 'prod', false, new NullLogger());
        $searcher = new AdminSearcher(
            $this->client,
            $this->registry,
            $searchHelper,
            $this->getDefinitionRegistry(),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            static::createStub(ElasticsearchHelper::class),
            '5s',
            20,
            'query_then_fetch',
        );

        $data = $searcher->search('elasticsearch', ['blog'], Context::createDefaultContext());

        static::assertSame([], $data);
    }

    public function testSearchThrowsWhenClientFailsAndExceptionsAreEnabled(): void
    {
        $exception = new \RuntimeException('No alive nodes found in your cluster');

        $this->client
            ->expects($this->once())
            ->method('msearch')
            ->willThrowException($exception);

        $this->expectExceptionObject($exception);

        $this->searcher->search('elasticsearch', ['blog'], Context::createDefaultContext());
    }

    private function createSearcher(AdminSearchRegistry&Stub $registry, DefinitionInstanceRegistry&Stub $definitionRegistry): AdminSearcher
    {
        return new AdminSearcher(
            $this->client,
            $registry,
            new AdminElasticsearchHelper(true, false, 'ct-admin', 'test', true, new NullLogger()),
            $definitionRegistry,
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            static::createStub(ElasticsearchHelper::class),
            '5s',
            20,
            'query_then_fetch',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function getQueryBody(string $query, string $timeout = '5s', ?string $tenantId = null, bool $platformOnly = true): array
    {
        $originalTerm = rtrim($query, '*');
        $splitTerms = explode(' ', $originalTerm);
        $lastPart = (string) end($splitTerms);
        $termLevelPrefixTerm = mb_strtolower($lastPart);
        $shouldQueries = [
            [
                'match' => [
                    'completion' => [
                        'query' => $originalTerm,
                        'boost' => SearchRanking::HIGH_SEARCH_RANKING,
                    ],
                ],
            ],
            [
                'match' => [
                    'completion.ngram' => [
                        'query' => $originalTerm,
                        'boost' => SearchRanking::LOW_SEARCH_RANKING,
                    ],
                ],
            ],
            [
                'prefix' => [
                    'completion' => [
                        'value' => $termLevelPrefixTerm,
                        'boost' => SearchRanking::MIDDLE_SEARCH_RANKING,
                    ],
                ],
            ],
            [
                'simple_query_string' => [
                    'query' => $query,
                    'fields' => ['text'],
                    'lenient' => true,
                    'boost' => SearchRanking::LOW_SEARCH_RANKING,
                ],
            ],
        ];

        $shouldQueries[] = [
            'simple_query_string' => [
                'query' => $query,
                'fields' => ['textBoosted'],
                'boost' => SearchRanking::HIGH_SEARCH_RANKING,
                'lenient' => true,
            ],
        ];

        $bool = ['should' => $shouldQueries];
        if ($tenantId !== null) {
            $bool['filter'] = [
                ['term' => ['tenantId' => $tenantId]],
            ];
        } elseif ($platformOnly) {
            $bool['filter'] = [[
                'bool' => [
                    'must_not' => [['exists' => ['field' => 'tenantId']]],
                ],
            ]];
        }

        return [
            'body' => [
                [
                    'index' => 'ct-admin-blog-listing',
                    'search_type' => 'query_then_fetch',
                    'allow_no_indices' => true,
                    'ignore_unavailable' => true,
                ],
                [
                    'query' => [
                        'bool' => $bool,
                    ],
                    'size' => 5,
                    'timeout' => $timeout,
                ],
            ],
        ];
    }

    private function getDefinitionRegistry(): DefinitionInstanceRegistry&Stub
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $definition = new BlogDefinition();
        $definition->compile($registry);
        $registry->method('getByEntityName')->willReturn($definition);

        return $registry;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function assertSearchResult(array $data, int $total, string $indexer, string $index): void
    {
        static::assertNotEmpty($data['blog']);
        static::assertSame($total, $data['blog']['total']);
        static::assertSame($indexer, $data['blog']['indexer']);
        static::assertSame($index, $data['blog']['index']);
    }

    /**
     * @return array<string, mixed>
     */
    private function getMockResponse(string $text): array
    {
        return [
            'took' => 42,
            'responses' => [
                [
                    'took' => 42,
                    'timed_out' => false,
                    '_shards' => [
                        'total' => 1,
                        'successful' => 1,
                        'skipped' => 0,
                        'failed' => 0,
                    ],
                    'hits' => [
                        'total' => [
                            'value' => 1,
                            'relation' => 'eq',
                        ],
                        'max_score' => 4.9525366,
                        'hits' => [
                            [
                                '_index' => 'ct-admin-blog-listing',
                                '_type' => '_doc',
                                '_id' => 'c1a28776116d4431a2208eb2960ec340',
                                '_score' => 4.9525366,
                                '_source' => [
                                    'entityName' => 'blog',
                                    'parameters' => [],
                                    'text' => $text,
                                    'id' => 'c1a28776116d4431a2208eb2960ec340',
                                ],
                            ],
                        ],
                    ],
                    'status' => 200,
                ],
            ],
        ];
    }
}
