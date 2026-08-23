<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework;

use OpenSearch\Client;
use OpenSearchDSL\Query\Compound\BoolQuery;
use OpenSearchDSL\Query\FullText\MatchQuery;
use OpenSearchDSL\Query\TermLevel\TermQuery;
use OpenSearchDSL\Search;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Contena\Core\Framework\DataAbstractionLayer\Field\TenantField;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Contena\Core\System\Language\LanguageDefinition;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Contena\Elasticsearch\Framework\ElasticsearchHelper;
use Contena\Elasticsearch\Framework\ElasticsearchRegistry;

/**
 * @internal
 */
#[CoversClass(ElasticsearchHelper::class)]
class ElasticsearchHelperTest extends TestCase
{
    public function testLogAndThrowException(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');
        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            true,
            static::createStub(Client::class),
            static::createStub(ElasticsearchRegistry::class),
            static::createStub(CriteriaParser::class),
            $logger,
            static::createStub(SystemConfigService::class),
        );

        static::expectException(\RuntimeException::class);

        static::assertFalse($helper->logAndThrowException(new \RuntimeException('test')));
    }

    public function testLogAndThrowExceptionOnlyLogs(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');
        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            false,
            static::createStub(Client::class),
            static::createStub(ElasticsearchRegistry::class),
            static::createStub(CriteriaParser::class),
            $logger,
            static::createStub(SystemConfigService::class),
        );

        $helper->logAndThrowException(new \RuntimeException('test'));
    }

    public function testAllowIndexingCatchesTransportFailures(): void
    {
        $client = static::createStub(Client::class);
        $client->method('ping')->willThrowException(new \RuntimeException('cURL error 6: Could not resolve host'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('critical');

        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            false,
            $client,
            static::createStub(ElasticsearchRegistry::class),
            static::createStub(CriteriaParser::class),
            $logger,
            static::createStub(SystemConfigService::class),
        );

        static::assertFalse($helper->allowIndexing());
    }

    public function testAllowIndexingRethrowsTransportFailuresWhenConfigured(): void
    {
        $client = static::createStub(Client::class);
        $client->method('ping')->willThrowException(new \RuntimeException('cURL error 6: Could not resolve host'));

        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            true,
            $client,
            static::createStub(ElasticsearchRegistry::class),
            static::createStub(CriteriaParser::class),
            static::createStub(LoggerInterface::class),
            static::createStub(SystemConfigService::class),
        );

        static::expectException(\RuntimeException::class);
        $helper->allowIndexing();
    }

    public function testGetIndexName(): void
    {
        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            true,
            static::createStub(Client::class),
            static::createStub(ElasticsearchRegistry::class),
            static::createStub(CriteriaParser::class),
            static::createStub(LoggerInterface::class),
            static::createStub(SystemConfigService::class),
        );

        static::assertSame('prefix_blog', $helper->getIndexName(new BlogDefinition()));
    }

    public function testAllowSearch(): void
    {
        $registry = static::createStub(ElasticsearchRegistry::class);
        $registry->method('has')->willReturnMap([
            ['blog', true],
            ['category', false],
        ]);

        $helper = new ElasticsearchHelper(
            'prod',
            true,
            true,
            'prefix',
            true,
            static::createStub(Client::class),
            $registry,
            static::createStub(CriteriaParser::class),
            static::createStub(LoggerInterface::class),
            static::createStub(SystemConfigService::class),
        );

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        static::assertTrue(
            $helper->allowSearch(new BlogDefinition(), Context::createDefaultContext(), $criteria)
        );

        static::assertFalse(
            $helper->allowSearch(new CategoryDefinition(), Context::createDefaultContext(), $criteria)
        );

        $helper->setEnabled(false);

        static::assertFalse(
            $helper->allowSearch(new BlogDefinition(), Context::createDefaultContext(), $criteria)
        );
    }

    public function testAddFiltersAppliesContextTenantBoundary(): void
    {
        $tenantAwareDefinition = new BlogDefinition();
        $this->setDefinitionFields($tenantAwareDefinition, [new TenantField()]);
        $sharedDefinition = new LanguageDefinition();
        $this->setDefinitionFields($sharedDefinition, []);

        $helper = new ElasticsearchHelper(
            'dev',
            true,
            true,
            'prefix',
            true,
            static::createStub(Client::class),
            static::createStub(ElasticsearchRegistry::class),
            static::createStub(CriteriaParser::class),
            static::createStub(LoggerInterface::class),
            static::createStub(SystemConfigService::class),
        );

        $platformSearch = new Search();
        $helper->addFilters($tenantAwareDefinition, new Criteria(), $platformSearch, Context::createDefaultContext());
        static::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [[
                        'bool' => [
                            'must_not' => [[
                                'exists' => ['field' => 'tenantId'],
                            ]],
                        ],
                    ]],
                ],
            ],
        ], $platformSearch->toArray());

        $tenantSearch = new Search();
        $helper->addFilters($tenantAwareDefinition, new Criteria(), $tenantSearch, Context::createTenantContext('tenant-id'));
        static::assertSame([
            'query' => [
                'bool' => [
                    'filter' => [[
                        'term' => ['tenantId' => 'tenant-id'],
                    ]],
                ],
            ],
        ], $tenantSearch->toArray());

        $globalSearch = new Search();
        $helper->addFilters($tenantAwareDefinition, new Criteria(), $globalSearch, Context::createGlobalContext());
        static::assertSame([], $globalSearch->toArray());

        $sharedEntitySearch = new Search();
        $helper->addFilters($sharedDefinition, new Criteria(), $sharedEntitySearch, Context::createTenantContext('tenant-id'));
        static::assertSame([], $sharedEntitySearch->toArray());
    }

    public function testAddQueries(): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('test_entity');

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new EqualsFilter('field', 'test'), 500));

        $search = $this->createMock(Search::class);
        $search->expects($this->once())->method('addQuery')->with(static::isInstanceOf(BoolQuery::class));

        $expectedParsed = new TermQuery('field', 'test');
        $parser = static::createStub(CriteriaParser::class);
        $parser->method('parseFilter')
            ->willReturnCallback(static function () use ($expectedParsed) {
                return $expectedParsed;
            });

        $helper = new ElasticsearchHelper(
            'dev',
            true,
            true,
            'prefix',
            true,
            static::createStub(Client::class),
            static::createStub(ElasticsearchRegistry::class),
            $parser,
            static::createStub(LoggerInterface::class),
            static::createStub(SystemConfigService::class),
        );

        $helper->addQueries($definition, $criteria, $search, $context);

        static::assertSame(['boost' => '500'], $expectedParsed->getParameters());
    }

    public function testAddQueriesWithTerm(): void
    {
        $definition = static::createStub(EntityDefinition::class);
        $definition->method('getEntityName')->willReturn('test_entity');

        $context = Context::createDefaultContext();

        $criteria = new Criteria();
        $criteria->setTerm('test');
        $criteria->addQuery(new ScoreQuery(new EqualsFilter('fieldB', 'bar'), 500));

        $search = new Search();

        $search->addQuery(new TermQuery('fieldA', 'bar'), BoolQuery::SHOULD);

        $expectedParsed = new MatchQuery('fieldB', 'bar', ['boost' => SearchRanking::HIGH_SEARCH_RANKING]);
        $parser = static::createStub(CriteriaParser::class);
        $parser->method('parseFilter')
            ->willReturnCallback(static function () use ($expectedParsed) {
                return $expectedParsed;
            });

        $helper = new ElasticsearchHelper(
            'dev',
            true,
            true,
            'prefix',
            true,
            static::createStub(Client::class),
            static::createStub(ElasticsearchRegistry::class),
            $parser,
            static::createStub(LoggerInterface::class),
            static::createStub(SystemConfigService::class),
        );

        $helper->addQueries($definition, $criteria, $search, $context);

        static::assertSame(['query' => [
            'bool' => [
                BoolQuery::SHOULD => [
                    ['term' => ['fieldA' => 'bar']],
                    ['match' => ['fieldB' => ['query' => 'bar', 'boost' => (string) SearchRanking::HIGH_SEARCH_RANKING, 'fuzziness' => '2']]],
                ],
            ],
        ]], $search->toArray());
    }

    /**
     * @param list<Field> $fields
     */
    private function setDefinitionFields(EntityDefinition $definition, array $fields): void
    {
        $compiledFields = new CompiledFieldCollection(static::createStub(DefinitionInstanceRegistry::class), $fields);
        new \ReflectionProperty(EntityDefinition::class, 'fields')->setValue($definition, $compiledFields);
    }
}
