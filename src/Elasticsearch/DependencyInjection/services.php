<?php declare(strict_types=1);

namespace Contena\Elasticsearch\DependencyInjection;

use Doctrine\DBAL\Connection;
use OpenSearch\Client;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Contena\Core\Content\Product\ProductDefinition;
use Contena\Core\Content\Product\SearchKeyword\ProductSearchBuilderInterface;
use Contena\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Contena\Core\System\CustomField\CustomFieldService;
use Contena\Core\System\Language\LanguageLoader;
use Contena\Core\System\Language\SalesChannelLanguageLoader;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Elasticsearch\AbstractFieldQueryBuilder;
use Contena\Elasticsearch\AbstractTokenQueryBuilder;
use Contena\Elasticsearch\Admin\AdminElasticsearchEntitySearcher;
use Contena\Elasticsearch\Admin\AdminElasticsearchHelper;
use Contena\Elasticsearch\Admin\AdminSearchController;
use Contena\Elasticsearch\Admin\AdminSearcher;
use Contena\Elasticsearch\Admin\AdminSearchRegistry;
use Contena\Elasticsearch\Admin\Indexer\CategoryAdminSearchIndexer;
use Contena\Elasticsearch\Admin\Indexer\CustomerAdminSearchIndexer;
use Contena\Elasticsearch\Admin\Indexer\CustomerGroupAdminSearchIndexer;
use Contena\Elasticsearch\Admin\Indexer\LandingPageAdminSearchIndexer;
use Contena\Elasticsearch\Admin\Indexer\ManufacturerAdminSearchIndexer;
use Contena\Elasticsearch\Admin\Indexer\MediaAdminSearchIndexer;
use Contena\Elasticsearch\Admin\Indexer\ProductAdminSearchIndexer;
use Contena\Elasticsearch\Admin\Indexer\ProductStreamAdminSearchIndexer;
use Contena\Elasticsearch\Admin\Indexer\PropertyGroupAdminSearchIndexer;
use Contena\Elasticsearch\Admin\Indexer\SalesChannelAdminSearchIndexer;
use Contena\Elasticsearch\Admin\Subscriber\RefreshIndexSubscriber;
use Contena\Elasticsearch\ExplainFieldQueryBuilder;
use Contena\Elasticsearch\FieldQueryBuilder;
use Contena\Elasticsearch\Framework\ClientFactory;
use Contena\Elasticsearch\Framework\Command\ElasticsearchAdminIndexingCommand;
use Contena\Elasticsearch\Framework\Command\ElasticsearchAdminResetCommand;
use Contena\Elasticsearch\Framework\Command\ElasticsearchAdminTestCommand;
use Contena\Elasticsearch\Framework\Command\ElasticsearchAdminUpdateMappingCommand;
use Contena\Elasticsearch\Framework\Command\ElasticsearchCleanIndicesCommand;
use Contena\Elasticsearch\Framework\Command\ElasticsearchCreateAliasCommand;
use Contena\Elasticsearch\Framework\Command\ElasticsearchIndexingCommand;
use Contena\Elasticsearch\Framework\Command\ElasticsearchResetCommand;
use Contena\Elasticsearch\Framework\Command\ElasticsearchStatusCommand;
use Contena\Elasticsearch\Framework\Command\ElasticsearchTestAnalyzerCommand;
use Contena\Elasticsearch\Framework\Command\ElasticsearchUpdateMappingCommand;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchAggregationHydrator;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\AbstractElasticsearchSearchHydrator;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\CriteriaParser;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregator;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntityAggregatorHydrator;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearcher;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchEntitySearchHydrator;
use Contena\Elasticsearch\Framework\DataAbstractionLayer\ElasticsearchTokenizer;
use Contena\Elasticsearch\Framework\ElasticsearchFieldBuilder;
use Contena\Elasticsearch\Framework\ElasticsearchFieldMapper;
use Contena\Elasticsearch\Framework\ElasticsearchHelper;
use Contena\Elasticsearch\Framework\ElasticsearchIndexingUtils;
use Contena\Elasticsearch\Framework\ElasticsearchLanguageProvider;
use Contena\Elasticsearch\Framework\ElasticsearchOutdatedIndexDetector;
use Contena\Elasticsearch\Framework\ElasticsearchRegistry;
use Contena\Elasticsearch\Framework\ElasticsearchStagingHandler;
use Contena\Elasticsearch\Framework\Indexing\CreateAliasTask;
use Contena\Elasticsearch\Framework\Indexing\CreateAliasTaskHandler;
use Contena\Elasticsearch\Framework\Indexing\ElasticsearchIndexer;
use Contena\Elasticsearch\Framework\Indexing\IndexCreator;
use Contena\Elasticsearch\Framework\Indexing\IndexManager;
use Contena\Elasticsearch\Framework\Indexing\IndexMappingProvider;
use Contena\Elasticsearch\Framework\Indexing\IndexMappingUpdater;
use Contena\Elasticsearch\Framework\Subscriber\InvalidateExpiredCacheSubscriber;
use Contena\Elasticsearch\Framework\SystemInstallListener;
use Contena\Elasticsearch\Framework\SystemUpdateListener;
use Contena\Elasticsearch\NestedFieldQueryBuilder;
use Contena\Elasticsearch\Product\AbstractProductSearchQueryBuilder;
use Contena\Elasticsearch\Product\CustomFieldSetGateway;
use Contena\Elasticsearch\Product\CustomFieldUpdater;
use Contena\Elasticsearch\Product\ElasticsearchCustomFieldsMappingHelper;
use Contena\Elasticsearch\Product\ElasticsearchOptimizeSwitch;
use Contena\Elasticsearch\Product\ElasticsearchProductDefinition;
use Contena\Elasticsearch\Product\LanguageSubscriber;
use Contena\Elasticsearch\Product\ProductCriteriaParser;
use Contena\Elasticsearch\Product\ProductCustomFieldsUsedUpdater;
use Contena\Elasticsearch\Product\ProductSearchBuilder;
use Contena\Elasticsearch\Product\ProductSearchQueryBuilder;
use Contena\Elasticsearch\Product\ProductUpdater;
use Contena\Elasticsearch\Product\SearchKeywordReplacement;
use Contena\Elasticsearch\Product\StopwordTokenFilter;
use Contena\Elasticsearch\Profiler\DataCollector;
use Contena\Elasticsearch\TokenQueryBuilder;
use Contena\Elasticsearch\TranslatedFieldQueryBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('elasticsearch.index.config', [
            'settings' => [
                'index' => '%elasticsearch.index_settings%',
                'analysis' => '%elasticsearch.analysis%',
            ],
        ])
        ->set('elasticsearch.index.mapping', [
            'dynamic_templates' => '%elasticsearch.dynamic_templates%',
        ])
        ->set('elasticsearch.administration.index.config', [
            'settings' => [
                'index' => '%elasticsearch.administration.index_settings%',
                'analysis' => '%elasticsearch.administration.analysis%',
            ],
        ])
        ->set('elasticsearch.administration.index.mapping', [
            'dynamic_templates' => '%elasticsearch.administration.dynamic_templates%',
        ]);

    $services = $containerConfigurator->services();

    $services->set(ElasticsearchTokenizer::class);

    $services->set(CriteriaParser::class)
        ->args([
            service(EntityDefinitionQueryHelper::class),
            service(CustomFieldService::class),
            service(AbstractKeyValueStorage::class),
        ]);

    $services->set(ElasticsearchHelper::class)
        ->public()
        ->args([
            param('kernel.environment'),
            param('elasticsearch.enabled'),
            param('elasticsearch.indexing_enabled'),
            param('elasticsearch.index_prefix'),
            param('elasticsearch.throw_exception'),
            service(Client::class),
            service(ElasticsearchRegistry::class),
            service(CriteriaParser::class),
            service('contena.elasticsearch.logger'),
            service(SystemConfigService::class),
        ]);

    $services->set(ElasticsearchIndexingUtils::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
            service('parameter_bag'),
        ]);

    $services->set(ElasticsearchFieldBuilder::class)
        ->args([
            service(LanguageLoader::class),
            service(ElasticsearchIndexingUtils::class),
            param('elasticsearch.language_analyzer_mapping'),
        ]);

    $services->set(ElasticsearchFieldMapper::class)
        ->args([
            service(ElasticsearchIndexingUtils::class),
        ]);

    $services->set(Client::class)
        ->public()
        ->lazy()
        ->factory([ClientFactory::class, 'createClient'])
        ->args([
            param('elasticsearch.hosts'),
            service('contena.elasticsearch.logger'),
            param('kernel.debug'),
            param('elasticsearch.ssl'),
        ]);

    $services->set('admin.openSearch.client', Client::class)
        ->public()
        ->lazy()
        ->factory([ClientFactory::class, 'createClient'])
        ->args([
            param('elasticsearch.administration.hosts'),
            service('contena.elasticsearch.logger'),
            param('kernel.debug'),
            param('elasticsearch.ssl'),
        ]);

    $services->set(IndexCreator::class)
        ->args([
            service(Client::class),
            param('elasticsearch.index.config'),
            service(IndexMappingProvider::class),
            service('event_dispatcher'),
            service(ElasticsearchHelper::class),
            param('elasticsearch.dimension_normalize'),
        ]);

    $services->set(IndexManager::class)
        ->args([
            service(Client::class),
            service(ElasticsearchHelper::class),
            service(ElasticsearchRegistry::class),
        ]);

    $services->set(InvalidateExpiredCacheSubscriber::class)
        ->args([
            service(IndexManager::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(IndexMappingProvider::class)
        ->args([
            param('elasticsearch.index.mapping'),
        ]);

    $services->set(IndexMappingUpdater::class)
        ->args([
            service(ElasticsearchRegistry::class),
            service(ElasticsearchHelper::class),
            service(Client::class),
            service(IndexMappingProvider::class),
            service(AbstractKeyValueStorage::class),
        ]);

    $services->set(ElasticsearchIndexingCommand::class)
        ->args([
            service(ElasticsearchIndexer::class),
            service('messenger.default_bus'),
            service(CreateAliasTaskHandler::class),
            param('elasticsearch.indexing_enabled'),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchTestAnalyzerCommand::class)
        ->args([
            service(Client::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchStatusCommand::class)
        ->args([
            service(Client::class),
            service(Connection::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchResetCommand::class)
        ->args([
            service(Client::class),
            service(ElasticsearchOutdatedIndexDetector::class),
            service(Connection::class),
            service('contena.increment.gateway.registry'),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchUpdateMappingCommand::class)
        ->args([
            service(IndexMappingUpdater::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchLanguageProvider::class)
        ->args([
            service('language.repository'),
            service('event_dispatcher'),
        ]);

    $services->set(ProductUpdater::class)
        ->args([
            service(ElasticsearchIndexer::class),
            service(ProductDefinition::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AbstractElasticsearchSearchHydrator::class, ElasticsearchEntitySearchHydrator::class);

    $services->set(AbstractElasticsearchAggregationHydrator::class, ElasticsearchEntityAggregatorHydrator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(ElasticsearchEntitySearcher::class)
        ->decorate(EntitySearcherInterface::class, null, 1000)
        ->public()
        ->args([
            service(Client::class),
            service(ElasticsearchEntitySearcher::class . '.inner'),
            service(ElasticsearchHelper::class),
            service(CriteriaParser::class),
            service(AbstractElasticsearchSearchHydrator::class),
            service('event_dispatcher'),
            param('elasticsearch.search.timeout'),
            param('elasticsearch.search.search_type'),
            param('elasticsearch.search.precision_threshold'),
        ]);

    $services->set(ElasticsearchEntityAggregator::class)
        ->decorate(EntityAggregatorInterface::class, null, 1000)
        ->public()
        ->args([
            service(ElasticsearchHelper::class),
            service(Client::class),
            service(ElasticsearchEntityAggregator::class . '.inner'),
            service(AbstractElasticsearchAggregationHydrator::class),
            service('event_dispatcher'),
            param('elasticsearch.search.timeout'),
            param('elasticsearch.search.search_type'),
        ]);

    $services->set(SearchKeywordReplacement::class)
        ->decorate(SearchKeywordUpdater::class, null, -50000)
        ->args([
            service(SearchKeywordReplacement::class . '.inner'),
            service(ElasticsearchHelper::class),
        ])
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(ProductSearchBuilder::class)
        ->decorate(ProductSearchBuilderInterface::class, null, -50000)
        ->args([
            service(ProductSearchBuilder::class . '.inner'),
            service(ElasticsearchHelper::class),
            service(ProductDefinition::class),
            param('elasticsearch.search.term_max_length'),
        ]);

    $services->set(CreateAliasTaskHandler::class)
        ->public()
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Client::class),
            service(Connection::class),
            service(ElasticsearchHelper::class),
            param('elasticsearch.index.config'),
            service('event_dispatcher'),
        ])
        ->tag('messenger.message_handler');

    $services->set(CreateAliasTask::class)
        ->tag('contena.scheduled.task');

    $services->set(ElasticsearchRegistry::class)
        ->args([
            tagged_iterator('contena.es.definition'),
        ]);

    $services->set(ElasticsearchStagingHandler::class)
        ->args([
            param('contena.staging.elasticsearch.check_for_existence'),
            service(ElasticsearchHelper::class),
            service(ElasticsearchOutdatedIndexDetector::class),
        ]);

    $services->set(ElasticsearchProductDefinition::class)
        ->args([
            service(ProductDefinition::class),
            service(Connection::class),
            service(AbstractProductSearchQueryBuilder::class),
            service(ElasticsearchFieldBuilder::class),
            service(ElasticsearchFieldMapper::class),
            service(SalesChannelLanguageLoader::class),
            param('elasticsearch.product.exclude_source'),
            param('kernel.environment'),
            service(LanguageLoader::class),
        ])
        ->tag('contena.es.definition');

    $services->set(StopwordTokenFilter::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(AbstractProductSearchQueryBuilder::class, ProductSearchQueryBuilder::class)
        ->args([
            service(ProductDefinition::class),
            service(StopwordTokenFilter::class),
            service(SearchConfigLoader::class),
            service(AbstractTokenQueryBuilder::class),
            service(ElasticsearchTokenizer::class),
        ]);

    // @deprecated tag:v6.8.0 Will be removed
    $services->alias(
        'Contena\Elasticsearch\Product\SearchConfigLoader',
        SearchConfigLoader::class,
    )->deprecate('contena/elasticsearch', '6.7.2.0', 'The "%alias_id%" service alias is deprecated and will be removed in v6.8.0. Use Contena\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader instead.');

    $services->set(AbstractFieldQueryBuilder::class, FieldQueryBuilder::class)
        ->args([
            param('elasticsearch.analysis.filter.sw_ngram_filter.min_gram'),
            param('elasticsearch.use_language_analyzer'),
            param('elasticsearch.search.dismax_tie_breaker'),
            param('elasticsearch.search.boost.exact'),
            param('elasticsearch.search.boost.phrase'),
            param('elasticsearch.search.boost.fuzzy'),
            param('elasticsearch.search.boost.prefix'),
            param('elasticsearch.search.boost.partial'),
        ]);

    $services->set(TranslatedFieldQueryBuilder::class)
        ->decorate(AbstractFieldQueryBuilder::class, null, 300)
        ->args([
            service(TranslatedFieldQueryBuilder::class . '.inner'),
            service(AbstractKeyValueStorage::class),
            param('elasticsearch.search.dismax_tie_breaker'),
        ]);

    $services->set(NestedFieldQueryBuilder::class)
        ->decorate(AbstractFieldQueryBuilder::class, null, 200)
        ->args([
            service(NestedFieldQueryBuilder::class . '.inner'),
        ]);

    $services->set(ExplainFieldQueryBuilder::class)
        ->decorate(AbstractFieldQueryBuilder::class, null, 100)
        ->args([
            service(ExplainFieldQueryBuilder::class . '.inner'),
        ]);

    $services->set(AbstractTokenQueryBuilder::class, TokenQueryBuilder::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(CustomFieldService::class),
            service(AbstractFieldQueryBuilder::class),
        ]);

    $services->alias(TokenQueryBuilder::class, AbstractTokenQueryBuilder::class);

    $services->set(CustomFieldUpdater::class)
        ->args([
            service(ElasticsearchHelper::class),
            service(CustomFieldSetGateway::class),
            service(ElasticsearchCustomFieldsMappingHelper::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CustomFieldSetGateway::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(ElasticsearchCustomFieldsMappingHelper::class)
        ->args([
            service(ElasticsearchOutdatedIndexDetector::class),
            service(Client::class),
            service(CustomFieldSetGateway::class),
        ]);

    $services->set(ProductCustomFieldsUsedUpdater::class)
        ->args([
            service(ElasticsearchHelper::class),
            service(ElasticsearchCustomFieldsMappingHelper::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ElasticsearchCreateAliasCommand::class)
        ->args([
            service(CreateAliasTaskHandler::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchCleanIndicesCommand::class)
        ->args([
            service(Client::class),
            service(ElasticsearchOutdatedIndexDetector::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchAdminIndexingCommand::class)
        ->args([
            service(AdminSearchRegistry::class),
        ])
        ->tag('console.command')
        ->tag('kernel.event_subscriber');

    $services->set(ElasticsearchAdminResetCommand::class)
        ->args([
            service('admin.openSearch.client'),
            service(Connection::class),
            service('contena.increment.gateway.registry'),
            service(AdminElasticsearchHelper::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchAdminTestCommand::class)
        ->args([
            service(AdminSearcher::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchAdminUpdateMappingCommand::class)
        ->args([
            service(AdminSearchRegistry::class),
        ])
        ->tag('console.command');

    $services->set(ElasticsearchOutdatedIndexDetector::class)
        ->args([
            service(Client::class),
            service(ElasticsearchRegistry::class),
            service(ElasticsearchHelper::class),
        ]);

    $services->set(ElasticsearchIndexer::class)
        ->args([
            service(Connection::class),
            service(ElasticsearchHelper::class),
            service(ElasticsearchRegistry::class),
            service(IndexCreator::class),
            service(IteratorFactory::class),
            service(Client::class),
            service('contena.elasticsearch.logger'),
            service('event_dispatcher'),
            param('elasticsearch.indexing_batch_size'),
            service(ClockInterface::class),
            param('elasticsearch.refresh_after_bulk'),
        ])
        ->tag('messenger.message_handler');

    $services->set(LanguageSubscriber::class)
        ->args([
            service(ElasticsearchHelper::class),
            service(ElasticsearchRegistry::class),
            service(Client::class),
            service(AbstractKeyValueStorage::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DataCollector::class)
        ->args([
            param('elasticsearch.enabled'),
            param('elasticsearch.administration.enabled'),
            service(Client::class),
            service('admin.openSearch.client'),
        ])
        ->tag('data_collector', ['template' => '@Elasticsearch/Collector/elasticsearch.html.twig', 'id' => 'elasticsearch']);

    $services->alias('contena.elasticsearch.logger', 'monolog.logger.elasticsearch');

    // This is required to prevent the 'Environment variables %VAR is never used' error
    $services->set('_dummy_es_env_usage', \ArrayIterator::class)
        ->lazy()
        ->public()
        ->args([
            [
                env('CONTENA_ES_ENABLED')->bool(),
                env('CONTENA_ES_INDEXING_ENABLED')->bool(),
                env('OPENSEARCH_URL')->string(),
                env('CONTENA_ES_INDEX_PREFIX')->string(),
                env('CONTENA_ES_THROW_EXCEPTION')->bool(),
                env('CONTENA_ES_INDEXING_BATCH_SIZE')->int(),
            ],
        ]);

    $services->set(RefreshIndexSubscriber::class)
        ->args([
            service(AdminSearchRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SystemInstallListener::class)
        ->args([
            service(ElasticsearchIndexer::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(SystemUpdateListener::class)
        ->args([
            service(AbstractKeyValueStorage::class),
            service(ElasticsearchIndexer::class),
            service('messenger.default_bus'),
            service(IndexMappingUpdater::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(AdminElasticsearchHelper::class)
        ->public()
        ->args([
            param('elasticsearch.administration.enabled'),
            param('elasticsearch.administration.refresh_indices'),
            param('elasticsearch.administration.index_prefix'),
            param('kernel.environment'),
            param('elasticsearch.administration.throw_exception'),
            service('contena.elasticsearch.logger'),
        ]);

    $services->set(AdminSearchController::class)
        ->public()
        ->args([
            service(AdminSearcher::class),
            service(DefinitionInstanceRegistry::class),
            service(JsonEntityEncoder::class),
            service(AdminElasticsearchHelper::class),
        ]);

    $services->set(AdminSearcher::class)
        ->args([
            service('admin.openSearch.client'),
            service(AdminSearchRegistry::class),
            service(AdminElasticsearchHelper::class),
            service(DefinitionInstanceRegistry::class),
            service(AbstractElasticsearchSearchHydrator::class),
            service(ElasticsearchHelper::class),
            param('elasticsearch.administration.search.timeout'),
            param('elasticsearch.administration.search.term_max_length'),
            param('elasticsearch.administration.search.search_type'),
        ]);

    $services->set(AdminSearchRegistry::class)
        ->args([
            tagged_iterator('contena.elastic.admin-searcher-index', 'key'),
            service(Connection::class),
            service('messenger.default_bus'),
            service('event_dispatcher'),
            service('admin.openSearch.client'),
            service(AdminElasticsearchHelper::class),
            service('contena.elasticsearch.logger'),
            param('elasticsearch.administration.index.config'),
            param('elasticsearch.administration.index.mapping'),
            param('kernel.environment'),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('messenger.message_handler');


    $services->set(CustomerAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('customer.repository'),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('contena.elastic.admin-searcher-index', ['key' => 'customer']);

    $services->set(CustomerGroupAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('customer_group.repository'),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('contena.elastic.admin-searcher-index', ['key' => 'customer_group']);

    $services->set(LandingPageAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('landing_page.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('contena.elastic.admin-searcher-index', ['key' => 'landing_page']);

    $services->set(ManufacturerAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('product_manufacturer.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('contena.elastic.admin-searcher-index', ['key' => 'product_manufacturer']);

    $services->set(MediaAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('media.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('contena.elastic.admin-searcher-index', ['key' => 'media']);



    $services->set(ProductAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('product.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('contena.elastic.admin-searcher-index', ['key' => 'product']);


    $services->set(PropertyGroupAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('property_group.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('contena.elastic.admin-searcher-index', ['key' => 'property_group']);

    $services->set(SalesChannelAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('sales_channel.repository'),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('contena.elastic.admin-searcher-index', ['key' => 'sales_channel']);


    $services->set(CategoryAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('category.repository'),
            service(ElasticsearchFieldBuilder::class),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('contena.elastic.admin-searcher-index', ['key' => 'category']);


    $services->set(ProductStreamAdminSearchIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('product_stream.repository'),
            param('elasticsearch.administration.indexing_batch_size'),
        ])
        ->tag('contena.elastic.admin-searcher-index', ['key' => 'product_stream']);

    $services->set(ProductCriteriaParser::class)
        ->decorate(CriteriaParser::class)
        ->args([
            service(EntityDefinitionQueryHelper::class),
            service(CustomFieldService::class),
            service(AbstractKeyValueStorage::class),
            service(ProductCriteriaParser::class . '.inner'),
        ]);

    $services->set(ElasticsearchOptimizeSwitch::class)
        ->args([
            service(AbstractKeyValueStorage::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(AdminElasticsearchEntitySearcher::class)
        ->decorate(EntitySearcherInterface::class, null, 500)
        ->public()
        ->args([
            service(AdminElasticsearchEntitySearcher::class . '.inner'),
            service(AdminSearchRegistry::class),
            service(AdminElasticsearchHelper::class),
            service(AdminSearcher::class),
            param('elasticsearch.administration.index_settings.max_result_window'),
        ]);
};
