<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Blog\SearchKeyword\KeywordLoader;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchTermInterpreter;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\Sync\SyncFkResolver;
use Contena\Core\Framework\Api\Sync\SyncService;
use Contena\Core\Framework\Api\Sync\Telemetry\SyncMetricsInstrumentor;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Command\CreateEntitiesCommand;
use Contena\Core\Framework\DataAbstractionLayer\Command\CreateHydratorCommand;
use Contena\Core\Framework\DataAbstractionLayer\Command\CreateMigrationCommand;
use Contena\Core\Framework\DataAbstractionLayer\Command\DataAbstractionLayerValidateCommand;
use Contena\Core\Framework\DataAbstractionLayer\Command\RefreshIndexCommand;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\CriteriaFieldsResolver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\CriteriaQueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityAggregator;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityForeignKeyResolver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityHydrator;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityReader;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\ExceptionHandlerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\ConfigJsonFieldAccessorBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\CustomFieldsAccessorBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\DefaultFieldAccessorBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\PriceFieldAccessorBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\CriteriaPartResolver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToManyAssociationFieldResolver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\ManyToOneAssociationFieldResolver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\OneToManyAssociationFieldResolver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldResolver\TranslationFieldResolver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\JoinGroupBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\SchemaBuilder;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionValidator;
use Contena\Core\Framework\DataAbstractionLayer\EntityGenerator;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEventFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\RepositoryWriterFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\Facade\ChannelRepositoryFacadeHookFactory;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\BlobFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\BoolFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\CalculatedPriceFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\CartPriceFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\CashRoundingConfigFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\ConfigJsonFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedAtFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\CreatedByFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\CronIntervalFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\CustomFieldsSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\DateFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\DateIntervalFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\DateTimeFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\EmailFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\EnumFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\FkFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\FloatFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\IntFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\ListFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToManyAssociationFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\ManyToOneAssociationFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToManyAssociationFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\OneToOneAssociationFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\PasswordFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\PHPUnserializeFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceDefinitionFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\ReferenceVersionFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\RemoteAddressFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\StateMachineStateFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\StringFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\TimeZoneFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslatedFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\TranslationsAssociationFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedAtFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedByFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\VariantListingConfigFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionDataPayloadFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\EntityIndexingSubscriber;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\Subscriber\RegisteredIndexerSubscriber;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\Telemetry\IndexerMetricsInstrumentor;
use Contena\Core\Framework\DataAbstractionLayer\MigrationFileRenderer;
use Contena\Core\Framework\DataAbstractionLayer\MigrationQueryGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\ApiCriteriaValidator;
use Contena\Core\Framework\DataAbstractionLayer\Search\CachedCompressedCriteriaDecoder;
use Contena\Core\Framework\DataAbstractionLayer\Search\CachedSearchConfigLoader;
use Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Contena\Core\Framework\DataAbstractionLayer\Search\CriteriaArrayConverter;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntityAggregatorInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\AggregationParser;
use Contena\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Filter\TokenFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Term\Tokenizer;
use Contena\Core\Framework\DataAbstractionLayer\TechnicalNameExceptionHandler;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\DalSearchInstrumentor;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityTelemetrySubscriber;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityExistsValidator;
use Contena\Core\Framework\DataAbstractionLayer\Validation\EntityNotExistsValidator;
use Contena\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Version\Cleanup\CleanupVersionTask;
use Contena\Core\Framework\DataAbstractionLayer\Version\Cleanup\CleanupVersionTaskHandler;
use Contena\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Contena\Core\Framework\DataAbstractionLayer\VersionManager;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriter;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityWriteResultFactory;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\ConstraintBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\LockValidator;
use Contena\Core\Framework\DataAbstractionLayer\Write\Validation\ParentRelationValidator;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteCommandExtractor;
use Contena\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Contena\Core\Framework\Script\AppContextCreator;
use Contena\Core\Framework\Telemetry\Metrics\Config\MetricConfigProvider;
use Contena\Core\Framework\Telemetry\Metrics\Meter;
use Contena\Core\Framework\Telemetry\Telemetry;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\System\CustomField\CustomFieldService;
use Contena\Core\System\Language\LanguageLoader;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\SearchConfigLoader;
use Contena\Core\Framework\Migration\IndexerQueuer;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        // @deprecated tag:v6.8.0 Will be removed, it's then always enabled
        ->set('env(CONTENA_DBAL_TIMEZONE_SUPPORT_ENABLED)', '0')
        ->set('contena.dbal.time_zone_support_enabled', env('CONTENA_DBAL_TIMEZONE_SUPPORT_ENABLED')->bool())
        // @deprecated tag:v6.8.0 Will be removed
        ->set('env(CONTENA_DBAL_TOKEN_MINIMUM_LENGTH)', '3')
        // @deprecated tag:v6.8.0 Will be removed
        ->set('contena.dbal.token_minimum_length', env('CONTENA_DBAL_TOKEN_MINIMUM_LENGTH')->int());

    $services = $containerConfigurator->services();

    $services->set(EntityGenerator::class);

    $services->set(CreateEntitiesCommand::class)
        ->args([
            service(EntityGenerator::class),
            service(DefinitionInstanceRegistry::class),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(CreateMigrationCommand::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(MigrationQueryGenerator::class),
            service('kernel'),
            service(Filesystem::class),
            service(MigrationFileRenderer::class),
            param('kernel.contena_core_dir'),
            param('kernel.contena_version'),
            service(ClockInterface::class),
        ])
        ->tag('console.command');

    $services->set(SchemaBuilder::class);

    $services->set(MigrationFileRenderer::class);

    $services->set(MigrationQueryGenerator::class)
        ->args([
            service(Connection::class),
            service(SchemaBuilder::class),
        ]);

    $services->set(EntityLoadedEventFactory::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(CreateHydratorCommand::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Filesystem::class),
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(EntityCacheKeyGenerator::class)
        ->public();

    $services->set(EntityDefinitionQueryHelper::class);

    $services->set(JoinGroupBuilder::class)
        ->public();

    $services->set(EntityHydrator::class)
        ->public()
        ->args([
            service('service_container'),
        ]);

    $services->set(DefinitionValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(Tokenizer::class)
        ->args([
            service(HtmlSanitizer::class),
            param('contena.dbal.token_minimum_length'),
            param('contena.search.preserved_chars'),
        ]);

    $services->set(SearchTermInterpreter::class)
        ->args([
            service(Tokenizer::class),
            param('contena.dbal.token_minimum_length'),
        ]);

    $services->set(EntityScoreQueryBuilder::class);

    $services->set(BlogSearchTermInterpreter::class)
        ->args([
            service(Connection::class),
            service(Tokenizer::class),
            service('logger'),
            service(TokenFilter::class),
            service(KeywordLoader::class),
            service(SearchConfigLoader::class),
            param('contena.blog.search_keyword.relevant_keyword_count'),
        ]);

    $services->set(KeywordLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set('api.request_criteria_builder', RequestCriteriaBuilder::class)
        ->args([
            service(AggregationParser::class),
            service(ApiCriteriaValidator::class),
            service(CriteriaArrayConverter::class),
            service(CompressedCriteriaDecoder::class),
            param('contena.api.max_limit'),
        ]);

    $services->set(SearchConfigLoader::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CachedSearchConfigLoader::class)
        ->decorate(SearchConfigLoader::class, null, -1000)
        ->args([
            service(CachedSearchConfigLoader::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(CriteriaArrayConverter::class)
        ->args([
            service(AggregationParser::class),
        ]);

    $services->set(CompressedCriteriaDecoder::class);

    $services->set(CachedCompressedCriteriaDecoder::class)
        ->decorate(CompressedCriteriaDecoder::class)
        ->args([
            service(CachedCompressedCriteriaDecoder::class . '.inner'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(RequestCriteriaBuilder::class)
        ->args([
            service(AggregationParser::class),
            service(ApiCriteriaValidator::class),
            service(CriteriaArrayConverter::class),
            service(CompressedCriteriaDecoder::class),
            param('contena.api.channel.max_limit'),
        ]);

    $services->set(ApiCriteriaValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(AggregationParser::class);

    $services->set(RepositoryFacadeHookFactory::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(AppContextCreator::class),
            service(RequestCriteriaBuilder::class),
            service(AclCriteriaValidator::class),
        ]);

    $services->set(RepositoryWriterFacadeHookFactory::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(AppContextCreator::class),
            service(SyncService::class),
        ]);

    $services->set(ChannelRepositoryFacadeHookFactory::class)
        ->public()
        ->args([
            service(ChannelDefinitionInstanceRegistry::class),
            service(RequestCriteriaBuilder::class),
        ]);

    // EntityDefinition[]
    $services->set(EntityReaderInterface::class, EntityReader::class)
        ->public()
        ->args([
            service(Connection::class),
            service(EntityHydrator::class),
            service(EntityDefinitionQueryHelper::class),
            service(SqlQueryParser::class),
            service(CriteriaQueryBuilder::class),
            service('logger'),
            service(CriteriaFieldsResolver::class),
        ]);

    $services->set(CriteriaFieldsResolver::class);

    $services->set(EntityAggregatorInterface::class, EntityAggregator::class)
        ->public()
        ->args([
            service(Connection::class),
            service(EntityDefinitionQueryHelper::class),
            service(DefinitionInstanceRegistry::class),
            service(CriteriaQueryBuilder::class),
            param('contena.dbal.time_zone_support_enabled'),
            service(SearchTermInterpreter::class),
            service(EntityScoreQueryBuilder::class),
        ]);

    $services->set(EntitySearcherInterface::class, EntitySearcher::class)
        ->public()
        ->args([
            service(Connection::class),
            service(EntityDefinitionQueryHelper::class),
            service(CriteriaQueryBuilder::class),
        ]);

    $services->set(CriteriaQueryBuilder::class)
        ->args([
            service(SqlQueryParser::class),
            service(EntityDefinitionQueryHelper::class),
            service(SearchTermInterpreter::class),
            service(EntityScoreQueryBuilder::class),
            service(JoinGroupBuilder::class),
            service(CriteriaPartResolver::class),
        ]);

    $services->set(CriteriaPartResolver::class)
        ->args([
            service(Connection::class),
            service(SqlQueryParser::class),
        ]);

    $services->set(EntityWriter::class)
        ->public()
        ->args([
            service(WriteCommandExtractor::class),
            service(EntityForeignKeyResolver::class),
            service(EntityWriteGatewayInterface::class),
            service(LanguageLoader::class),
            service(DefinitionInstanceRegistry::class),
            service(EntityWriteResultFactory::class),
        ]);

    $services->set(EntityWriteResultFactory::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(WriteCommandExtractor::class)
        ->args([
            service(EntityWriteGatewayInterface::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(EntityWriteGatewayInterface::class, EntityWriteGateway::class)
        ->public()
        ->args([
            param('contena.dal.batch_size'),
            service(Connection::class),
            service('event_dispatcher'),
            service(ExceptionHandlerRegistry::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(ConstraintBuilder::class);

    $services->set(SqlQueryParser::class)
        ->args([
            service(EntityDefinitionQueryHelper::class),
            service(Connection::class),
        ]);

    $services->set(EntityForeignKeyResolver::class)
        ->args([
            service(Connection::class),
            service(EntityDefinitionQueryHelper::class),
        ]);

    $services->set(ManyToOneAssociationFieldResolver::class)
        ->args([
            service(EntityDefinitionQueryHelper::class),
            service(Connection::class),
        ])
        ->tag('contena.field_resolver', ['priority' => -50]);

    $services->set(OneToManyAssociationFieldResolver::class)
        ->tag('contena.field_resolver', ['priority' => -50]);

    $services->set(ManyToManyAssociationFieldResolver::class)
        ->tag('contena.field_resolver', ['priority' => -50]);

    $services->set(TranslationFieldResolver::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.field_resolver', ['priority' => -50]);

    $services->set(PriceFieldAccessorBuilder::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.field_accessor_builder', ['priority' => -100]);

    $services->set(JsonFieldAccessorBuilder::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.field_accessor_builder', ['priority' => -150]);

    $services->set(DefaultFieldAccessorBuilder::class)
        ->tag('contena.field_accessor_builder', ['priority' => -200]);

    $services->set(ConfigJsonFieldAccessorBuilder::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.field_accessor_builder', ['priority' => -100]);

    $services->set(CustomFieldsAccessorBuilder::class)
        ->args([
            service(CustomFieldService::class),
            service(Connection::class),
        ])
        ->tag('contena.field_accessor_builder', ['priority' => -100]);

    $services->set(VersionDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(VersionCommitDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(VersionCommitDataDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(RefreshIndexCommand::class)
        ->args([
            service(EntityIndexerRegistry::class),
            service('event_dispatcher'),
            service('messenger.default_bus'),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('console.command');

    $services->set(RegisteredIndexerSubscriber::class)
        ->args([
            service(IndexerQueuer::class),
            service(EntityIndexerRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(DataAbstractionLayerValidateCommand::class)
        ->args([
            service(DefinitionValidator::class),
        ])
        ->tag('console.command');

    $services->set(VersionManager::class)
        ->public()
        ->args([
            service(EntityWriter::class),
            service(EntityReaderInterface::class),
            service(EntitySearcherInterface::class),
            service(EntityWriteGatewayInterface::class),
            service('event_dispatcher'),
            service('serializer'),
            service(DefinitionInstanceRegistry::class),
            service(VersionCommitDefinition::class),
            service(VersionCommitDataDefinition::class),
            service(VersionDefinition::class),
            service('lock.factory'),
            service(ClockInterface::class),
        ]);

    $services->set(CalculatedPriceFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(CartPriceFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(CashRoundingConfigFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(PriceDefinitionFieldSerializer::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('validator'),
            service(RuleConditionRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(BoolFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');


    $services->set(CreatedAtFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(ClockInterface::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(DateFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(DateTimeFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(EmailFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(EnumFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(FkFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(StateMachineStateFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(FloatFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(IdFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(IntFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(RemoteAddressFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(SystemConfigService::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(JsonFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(ConfigJsonFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(LongTextFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(HtmlSanitizer::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(ListFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(ManyToManyAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(ManyToOneAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(OneToOneAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(BlobFieldSerializer::class)
        ->tag('contena.field_serializer');

    $services->set(OneToManyAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(EntityWriteGatewayInterface::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(PasswordFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(SystemConfigService::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(PHPUnserializeFieldSerializer::class)
        ->tag('contena.field_serializer');

    $services->set(PriceFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(VariantListingConfigFieldSerializer::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('validator'),
        ])
        ->tag('contena.field_serializer');

    $services->set(ReferenceVersionFieldSerializer::class)
        ->tag('contena.field_serializer');

    $services->set(StringFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(HtmlSanitizer::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(TranslatedFieldSerializer::class)
        ->tag('contena.field_serializer');

    $services->set(TranslationsAssociationFieldSerializer::class)
        ->args([
            service(WriteCommandExtractor::class),
            service(EntityWriteGatewayInterface::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(UpdatedAtFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
            service(ClockInterface::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(VersionDataPayloadFieldSerializer::class)
        ->tag('contena.field_serializer');

    $services->set(VersionFieldSerializer::class)
        ->tag('contena.field_serializer');

    $services->set(CustomFieldsSerializer::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('validator'),
            service(CustomFieldService::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(CreatedByFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(UpdatedByFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(TimeZoneFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(CronIntervalFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(DateIntervalFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(EntityExistsValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(EntitySearcherInterface::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(EntityNotExistsValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(EntitySearcherInterface::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(IteratorFactory::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(DefinitionInstanceRegistry::class)
        ->public()
        ->args([
            service('service_container'),
            [],
            [],
        ]);

    $services->set(LockValidator::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ParentRelationValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SyncService::class)
        ->public()
        ->args([
            service(EntityWriter::class),
            service('event_dispatcher'),
            service(DefinitionInstanceRegistry::class),
            service(EntitySearcherInterface::class),
            service(RequestCriteriaBuilder::class),
            service(AclCriteriaValidator::class),
            service(SyncFkResolver::class),
            service(SyncMetricsInstrumentor::class),
        ]);

    $services->set(SyncMetricsInstrumentor::class)
        ->args([
            service(Telemetry::class),
            service(EntityGroupResolver::class),
        ]);

    $services->set(SyncFkResolver::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            tagged_iterator('contena.sync.fk_resolver'),
        ]);

    $services->set(ExceptionHandlerRegistry::class)
        ->args([
            tagged_iterator('contena.dal.exception_handler'),
        ]);

    $services->set(TechnicalNameExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(EntityProtectionValidator::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(EntityIndexerRegistry::class)
        ->public()
        ->args([
            tagged_iterator('contena.entity_indexer'),
            service('messenger.default_bus'),
            service('event_dispatcher'),
            service(IndexerMetricsInstrumentor::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(IndexerMetricsInstrumentor::class)
        ->args([
            service(Telemetry::class),
        ]);

    $services->set(EntityIndexingSubscriber::class)
        ->args([
            service(EntityIndexerRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(InheritanceUpdater::class)
        ->args([
            service(Connection::class),
            service(DefinitionInstanceRegistry::class),
        ]);

    $services->set(ChildCountUpdater::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(ManyToManyIdFieldUpdater::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(CleanupVersionTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CleanupVersionTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            param('contena.dal.versioning.expire_days'),
            service(ClockInterface::class),
            service(EventDispatcherInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(EntityTelemetrySubscriber::class)
        ->args([
            service(Meter::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('contena.telemetry.subscriber');

    // shared entity-name bucketing for telemetry labels (DAL search collectors + HTTP request domain)
    $services->set(EntityGroupResolver::class);

    // injected into every EntityRepository by EntityCompilerPass; self-gates on the telemetry flag
    $services->set(DalSearchInstrumentor::class)
        ->args([
            service(Meter::class),
            service(EntityGroupResolver::class),
            service(MetricConfigProvider::class),
            param('contena.telemetry.metrics.enabled'),
        ]);
};
