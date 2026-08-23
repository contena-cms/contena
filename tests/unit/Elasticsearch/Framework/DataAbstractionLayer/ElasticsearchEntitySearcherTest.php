<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Elasticsearch\Framework\DataAbstractionLayer;

use OpenSearch\Client;
use OpenSearch\Exception\RuntimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Contena\Core\System\CustomField\CustomFieldService;
use Contena\Elasticsearch\ElasticsearchException;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchSearchHydrator;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherSearchedEvent;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\Event\ElasticsearchEntitySearcherSearchEvent;
use Contena\Elasticsearch\Framework\ElasticsearchHelper;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ElasticsearchEntitySearcher::class)]
class ElasticsearchEntitySearcherTest extends TestCase
{
    public function testEmptyQueryExceptionIsCatched(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);

        $client = $this->createMock(Client::class);
        // client should not be used if limit is 0
        $client->expects($this->never())
            ->method('search');

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);
        $helper
            ->method('addTerm')
            ->willThrowException(ElasticsearchException::emptyQuery());

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $searcher->search(
            new BlogDefinition(),
            $criteria,
            $context
        );

        static::assertSame(0, $result->getTotal());
    }

    public function testWithCriteriaLimitOfZero(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(0);

        $client = $this->createMock(Client::class);
        // client should not be used if limit is 0
        $client->expects($this->never())
            ->method('search');

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '5s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $searcher->search(
            new BlogDefinition(),
            $criteria,
            $context
        );

        static::assertSame(0, $result->getTotal());
    }

    public function testSearchWithCount(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);

        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('search')->with([
                'index' => '',
                'body' => [
                    'timeout' => '10s',
                    'from' => 0,
                    'size' => 10,
                    'track_total_hits' => true,
                ],
                'search_type' => 'dfs_query_then_fetch',
            ])->willReturn([]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $searcher->search(
            new BlogDefinition(),
            $criteria,
            $context
        );
    }

    public function testSearchWithNoCount(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('search')->with([
                'index' => '',
                'body' => [
                    'timeout' => '10s',
                    'from' => 0,
                    'size' => 10,
                    'track_total_hits' => false,
                ],
                'search_type' => 'dfs_query_then_fetch',
            ])->willReturn([]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher->search(
            new BlogDefinition(),
            $criteria,
            $context
        );
    }

    public function testSearchWithExplainMode(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);

        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('search')->with([
                'index' => '',
                'include_named_queries_score' => true,
                'track_scores' => true,
                'body' => [
                    'timeout' => '10s',
                    'from' => 0,
                    'size' => 10,
                    'explain' => true,
                    'track_total_hits' => false,
                ],
                'search_type' => 'dfs_query_then_fetch',
            ])->willReturn([]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();
        $context->addState(Context::ELASTICSEARCH_EXPLAIN_MODE);

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher->search(
            new BlogDefinition(),
            $criteria,
            $context
        );
    }

    public function testDispatchEvents(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $context = Context::createDefaultContext();

        $client = $this->createMock(Client::class);

        $client->expects($this->once())
            ->method('search')->with([
                'index' => '',
                'body' => [
                    'timeout' => '10s',
                    'from' => 0,
                    'size' => 10,
                    'track_total_hits' => false,
                ],
                'search_type' => 'dfs_query_then_fetch',
            ])->willReturn([
                'hits' => [
                    'hits' => [],
                ],
            ]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper
            ->method('allowSearch')
            ->willReturn(true);

        $dispatcher = new EventDispatcher();
        $searchEventDispatched = false;
        $searchedEventDispatched = false;

        $dispatcher->addListener(ElasticsearchEntitySearcherSearchEvent::class, static function (ElasticsearchEntitySearcherSearchEvent $event) use (&$searchEventDispatched): void {
            $searchEventDispatched = true;
        });

        $dispatcher->addListener(ElasticsearchEntitySearcherSearchedEvent::class, static function (ElasticsearchEntitySearcherSearchedEvent $event) use (&$searchedEventDispatched): void {
            $searchedEventDispatched = true;
        });

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            static::createStub(CriteriaParser::class),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            $dispatcher,
            '10s',
            'dfs_query_then_fetch'
        );

        $searcher->search(
            new BlogDefinition(),
            $criteria,
            $context
        );

        static::assertTrue($searchEventDispatched);
        static::assertTrue($searchedEventDispatched);
    }

    public function testExceptionsGetLogged(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $client = $this->createMock(Client::class);
        // client should not be used if limit is 0
        $client->expects($this->once())
            ->method('search')
            ->willThrowException(new RuntimeException());

        $helper = $this->createMock(ElasticsearchHelper::class);
        $helper->expects($this->once())->method('logAndThrowException');
        $helper->method('allowSearch')->willReturn(true);

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            new CriteriaParser(new EntityDefinitionQueryHelper(), static::createStub(CustomFieldService::class)),
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '5s',
            'dfs_query_then_fetch'
        );

        $context = Context::createDefaultContext();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $result = $searcher->search(
            new BlogDefinition(),
            $criteria,
            $context
        );

        static::assertSame(0, $result->getTotal());
    }

    public function testSearchWithGroupingDoesNotSendPrecisionThresholdByDefault(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->addGroupField(new FieldGrouping('id'));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(static::callback(static function (array $params): bool {
                $cardinality = $params['body']['aggregations']['total-count']['cardinality'] ?? null;

                return \is_array($cardinality)
                    && ($cardinality['field'] ?? null) === 'id'
                    && !\array_key_exists('precision_threshold', $cardinality);
            }))
            ->willReturn([]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper->method('allowSearch')->willReturn(true);
        $helper->method('getIndexName')->willReturn('');

        $criteriaParser = static::createStub(CriteriaParser::class);
        $criteriaParser->method('buildAccessor')->willReturn('id');

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            $criteriaParser,
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch',
            null
        );

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher->search(new BlogDefinition(), $criteria, Context::createDefaultContext());
    }

    public function testSearchWithGroupingSendsConfiguredPrecisionThreshold(): void
    {
        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->addGroupField(new FieldGrouping('id'));
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $client = $this->createMock(Client::class);
        $client->expects($this->once())
            ->method('search')
            ->with(static::callback(static function (array $params): bool {
                $cardinality = $params['body']['aggregations']['total-count']['cardinality'] ?? null;

                return \is_array($cardinality)
                    && ($cardinality['field'] ?? null) === 'id'
                    && ($cardinality['precision_threshold'] ?? null) === 40000;
            }))
            ->willReturn([]);

        $helper = static::createStub(ElasticsearchHelper::class);
        $helper->method('allowSearch')->willReturn(true);
        $helper->method('getIndexName')->willReturn('');

        $criteriaParser = static::createStub(CriteriaParser::class);
        $criteriaParser->method('buildAccessor')->willReturn('id');

        $searcher = new ElasticsearchEntitySearcher(
            $client,
            static::createStub(EntitySearcherInterface::class),
            $helper,
            $criteriaParser,
            static::createStub(AbstractElasticsearchSearchHydrator::class),
            new EventDispatcher(),
            '10s',
            'dfs_query_then_fetch',
            40000
        );

        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $searcher->search(new BlogDefinition(), $criteria, Context::createDefaultContext());
    }
}
