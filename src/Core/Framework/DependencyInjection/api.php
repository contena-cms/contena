<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Content\Flow\Api\FlowActionCollector;
use Contena\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Contena\Core\Content\Media\Upload\PresignedMediaUploadService;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\ApiDefinition\Generator\AllChannelApiSchemaMigrationScopeProvider;
use Contena\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Contena\Core\Framework\Api\ApiDefinition\Generator\CachedEntitySchemaGenerator;
use Contena\Core\Framework\Api\ApiDefinition\Generator\ChannelApiGenerator;
use Contena\Core\Framework\Api\ApiDefinition\Generator\ChannelApiSchemaMigrationReporter;
use Contena\Core\Framework\Api\ApiDefinition\Generator\ChannelApiSchemaMigrationScopeProviderInterface;
use Contena\Core\Framework\Api\ApiDefinition\Generator\CoreChannelApiSchemaMigrationScopeProvider;
use Contena\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApiRouteDefaultsFilter;
use Contena\Core\Framework\Api\Command\ChannelApiSchemaMigrationReportCommand;
use Contena\Core\Framework\Api\Command\CreateIntegrationCommand;
use Contena\Core\Framework\Api\Command\DumpClassSchemaCommand;
use Contena\Core\Framework\Api\Command\DumpSchemaCommand;
use Contena\Core\Framework\Api\Context\ContextValueResolver;
use Contena\Core\Framework\Api\Controller\AccessKeyController;
use Contena\Core\Framework\Api\Controller\ApiController;
use Contena\Core\Framework\Api\Controller\AuthController;
use Contena\Core\Framework\Api\Controller\CacheController;
use Contena\Core\Framework\Api\Controller\ChannelProxyController;
use Contena\Core\Framework\Api\Controller\FallbackController;
use Contena\Core\Framework\Api\Controller\FeatureFlagController;
use Contena\Core\Framework\Api\Controller\HealthCheckController;
use Contena\Core\Framework\Api\Controller\IndexingController;
use Contena\Core\Framework\Api\Controller\InfoController;
use Contena\Core\Framework\Api\Controller\IntegrationController;
use Contena\Core\Framework\Api\Controller\SyncController;
use Contena\Core\Framework\Api\Controller\UserController;
use Contena\Core\Framework\Api\EventListener\Authentication\ApiAuthenticationListener;
use Contena\Core\Framework\Api\EventListener\Authentication\UserCredentialsChangedSubscriber;
use Contena\Core\Framework\Api\EventListener\CorsListener;
use Contena\Core\Framework\Api\EventListener\ExpectationSubscriber;
use Contena\Core\Framework\Api\EventListener\JsonRequestTransformerListener;
use Contena\Core\Framework\Api\EventListener\ResponseExceptionListener;
use Contena\Core\Framework\Api\EventListener\ResponseHeaderListener;
use Contena\Core\Framework\Api\OAuth\AccessTokenRepository;
use Contena\Core\Framework\Api\OAuth\ClientRepository;
use Contena\Core\Framework\Api\OAuth\FakeCryptKey;
use Contena\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Contena\Core\Framework\Api\OAuth\Scope\AdminScope;
use Contena\Core\Framework\Api\OAuth\Scope\UserVerifiedScope;
use Contena\Core\Framework\Api\OAuth\Scope\WriteScope;
use Contena\Core\Framework\Api\OAuth\ScopeRepository;
use Contena\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Contena\Core\Framework\Api\OAuth\UserRepository;
use Contena\Core\Framework\Api\Response\ResponseFactoryInterfaceValueResolver;
use Contena\Core\Framework\Api\Response\ResponseFactoryRegistry;
use Contena\Core\Framework\Api\Response\Type\Api\JsonApiType;
use Contena\Core\Framework\Api\Response\Type\Api\JsonType;
use Contena\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Contena\Core\Framework\Api\Route\ApiRouteLoader;
use Contena\Core\Framework\Api\Serializer\JsonApiDecoder;
use Contena\Core\Framework\Api\Serializer\JsonApiEncoder;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\Api\Sync\SyncService;
use Contena\Core\Framework\ContentSystem\Adapter\RootSourceRegistry;
use Contena\Core\Framework\ContentSystem\Binding\Registry\ContentSystemBindingSpecificationRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Element\Style\Registry\ContentSystemStyleOptionRegistry;
use Contena\Core\Framework\ContentSystem\Layout\Type\Registry\ContentSystemElementTypeRegistry;
use Contena\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderSchemaGenerator;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\Feature\FeatureFlagRegistry;
use Contena\Core\Framework\MessageQueue\Stats\StatsService;
use Contena\Core\Framework\Migration\MigrationInfo;
use Contena\Core\Framework\Routing\RequestTransformer;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\Framework\Routing\RouteScopeRegistry;
use Contena\Core\Framework\Rule\Api\RuleConfigController;
use Contena\Core\Framework\SystemCheck\SystemChecker;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\HappyPathValidator;
use Contena\Core\System\Channel\Api\StructEncoder;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\UserDefinition;
use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration as JWTConfiguration;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RuleConfigController::class)
        ->args([tagged_iterator('contena.rule.condition')])
        ->public();

    $services->set(RequestTransformerInterface::class, RequestTransformer::class)
        ->public();

    $services->set(FallbackController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(CorsListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(ResponseExceptionListener::class)
        ->args([
            param('kernel.debug'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ResponseHeaderListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(ContextValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1000]);

    $services->set(AccessKeyController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(ApiController::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('serializer'),
            service('api.request_criteria_builder'),
            service(EntityProtectionValidator::class),
            service(AclCriteriaValidator::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ChannelProxyController::class)
        ->public()
        ->args([
            service('kernel'),
            service('channel.repository'),
            service(ChannelContextServiceInterface::class),
            service('request_stack'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(SyncController::class)
        ->public()
        ->args([
            service(SyncService::class),
            service('serializer'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(HealthCheckController::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service(SystemChecker::class),
            service(SymfonyBearerTokenValidator::class),
            param('contena.api.static_token.health_check'),
        ]);

    $services->set(IndexingController::class)
        ->public()
        ->args([
            service(EntityIndexerRegistry::class),
            service('messenger.default_bus'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(DumpSchemaCommand::class)
        ->args([
            service(DefinitionService::class),
            service('cache.object'),
        ])
        ->tag('console.command');

    $services->set(DumpClassSchemaCommand::class)
        ->args([
            param('kernel.bundles_metadata'),
        ])
        ->tag('console.command');

    $services->set(CreateIntegrationCommand::class)
        ->args([
            service('integration.repository'),
        ])
        ->tag('console.command');

    $services->set(ChannelApiSchemaMigrationReportCommand::class)
        ->args([
            service(ChannelApiSchemaMigrationReporter::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('console.command');

    $services->set(JsonApiDecoder::class)
        ->tag('serializer.encoder');

    $services->set(ResponseFactoryRegistry::class)
        ->args([
            service(JsonApiType::class),
            // deactivated, the current sales channel api design does not match the json api requirements
            service(JsonType::class),
        ]);

    $services->set(JsonApiType::class)
        ->args([
            service(JsonApiEncoder::class),
            service(StructEncoder::class),
        ]);

    $services->set(JsonApiEncoder::class);

    $services->set(JsonEntityEncoder::class)
        ->args([
            service('serializer'),
        ]);

    $services->set(JsonType::class)
        ->args([
            service(JsonEntityEncoder::class),
            service('serializer'),
        ]);

    $services->set(DefinitionService::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(ChannelApiGenerator::class),
            service(OpenApi3Generator::class),
            service(EntitySchemaGenerator::class),
        ]);

    $services->set(OpenApiDefinitionSchemaBuilder::class)
        ->args([
            tagged_iterator('contena.api.enum_provider'),
        ]);

    $services->set(OpenApiPathBuilder::class);

    $services->set(OpenApiSchemaBuilder::class)
        ->args([
            param('kernel.contena_version'),
        ]);

    $services->set(BundleSchemaPathCollection::class)
        ->args([
            service('kernel.bundles'),
        ]);

    $services->set(OpenApi3Generator::class)
        ->args([
            service(OpenApiSchemaBuilder::class),
            service(OpenApiPathBuilder::class),
            service(OpenApiDefinitionSchemaBuilder::class),
            param('kernel.bundles_metadata'),
            service(BundleSchemaPathCollection::class),
            service(OpenApiRouteDefaultsFilter::class),
        ]);

    $services->set(OpenApiRouteDefaultsFilter::class)
        ->args([
            service('router'),
        ]);

    $services->set(ChannelApiGenerator::class)
        ->args([
            service(OpenApiSchemaBuilder::class),
            service(OpenApiDefinitionSchemaBuilder::class),
            param('kernel.bundles_metadata'),
            service(BundleSchemaPathCollection::class),
            service(OpenApiRouteDefaultsFilter::class),
        ]);

    $services->set(CoreChannelApiSchemaMigrationScopeProvider::class)
        ->tag(ChannelApiSchemaMigrationScopeProviderInterface::SERVICE_TAG);

    $services->set(AllChannelApiSchemaMigrationScopeProvider::class)
        ->args([
            service(BundleSchemaPathCollection::class),
        ])
        ->tag(ChannelApiSchemaMigrationScopeProviderInterface::SERVICE_TAG);

    $services->set(ChannelApiSchemaMigrationReporter::class)
        ->args([
            service(OpenApiDefinitionSchemaBuilder::class),
            tagged_iterator(ChannelApiSchemaMigrationScopeProviderInterface::SERVICE_TAG),
        ]);

    $services->set(EntitySchemaGenerator::class);

    $services->set(CachedEntitySchemaGenerator::class)
        ->decorate(EntitySchemaGenerator::class)
        ->args([
            service(CachedEntitySchemaGenerator::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(InfoController::class)
        ->public()
        ->args([
            service(DefinitionService::class),
            service('parameter_bag'),
            service(MigrationInfo::class),
            service(SystemConfigService::class),
            service(ApiRouteInfoResolver::class),
            service(StatsService::class),
            service('event_dispatcher'),
            service(ContentSystemDataLoaderSchemaGenerator::class),
            service(ContentSystemElementTypeRegistry::class),
            service(ContentSystemStyleOptionRegistry::class),
            service(RootSourceRegistry::class),
            service(ContentSystemBindingSpecificationRegistry::class),
            service(PresignedMediaUploadService::class)->nullOnInvalid(),
            service(MediaFileExtensionListProvider::class),
            service(BusinessEventCollector::class),
            service(FlowActionCollector::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AuthController::class)
        ->public()
        ->args([
            service('contena.api.authorization_server'),
            service(PsrHttpFactory::class),
            service('contena.rate_limiter'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(CacheController::class)
        ->public()
        ->args([
            service(CacheClearer::class),
            service(CacheInvalidator::class),
            service('cache.object'),
            service(EntityIndexerRegistry::class),
            service('event_dispatcher'),
        ])
        ->tag('container.service_subscriber')
        ->tag('controller.service_arguments')
        ->call('setContainer', [service(ContainerInterface::class)]);

    $services->set(AccessTokenRepository::class);

    $services->set(ClientRepository::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(RefreshTokenRepository::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(ScopeRepository::class)
        ->args([
            tagged_iterator('contena.oauth.scope'),
            service(Connection::class),
        ]);

    $services->set(UserRepository::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
            service('request_stack'),
        ]);

    $services->set(WriteScope::class)
        ->tag('contena.oauth.scope');

    $services->set(AdminScope::class)
        ->tag('contena.oauth.scope');

    $services->set(UserVerifiedScope::class)
        ->tag('contena.oauth.scope');

    $services->set('contena.jwt_config', JWTConfiguration::class)
        ->factory([JWTConfigurationFactory::class, 'createJWTConfiguration']);

    $services->set(FakeCryptKey::class)
        ->args([
            service('contena.jwt_config'),
        ]);

    $services->set('contena.api.authorization_server', AuthorizationServer::class)
        ->args([
            service(ClientRepository::class),
            service(AccessTokenRepository::class),
            service(ScopeRepository::class),
            service(FakeCryptKey::class),
            env('APP_SECRET'),
        ]);

    $services->set(HttpFoundationFactory::class);

    $services->set(SymfonyBearerTokenValidator::class)
        ->args([
            service(AccessTokenRepository::class),
            service(Connection::class),
            service('contena.jwt_config'),
        ]);

    $services->set(JsonRequestTransformerListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(ExpectationSubscriber::class)
        ->args([
            param('kernel.contena_version'),
            param('kernel.plugin_infos'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ApiAuthenticationListener::class)
        ->args([
            service(SymfonyBearerTokenValidator::class),
            service('contena.api.authorization_server'),
            service(UserRepository::class),
            service(RefreshTokenRepository::class),
            service(RouteScopeRegistry::class),
            param('contena.api.access_token_ttl'),
            param('contena.api.refresh_token_ttl'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserCredentialsChangedSubscriber::class)
        ->args([
            service(RefreshTokenRepository::class),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(UserController::class)
        ->public()
        ->args([
            service('user.repository'),
            service('acl_user_role.repository'),
            service('acl_role.repository'),
            service('user_access_key.repository'),
            service('user_tenant.repository'),
            service(UserDefinition::class),
            service(RefreshTokenRepository::class),
            service(AbstractNumberRangeValueGenerator::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(IntegrationController::class)
        ->public()
        ->args([
            service('integration.repository'),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(ResponseFactoryInterfaceValueResolver::class)
        ->args([
            service(ResponseFactoryRegistry::class),
        ])
        ->tag('controller.argument_value_resolver', ['priority' => 50]);

    $services->set(ApiRouteLoader::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('routing.loader');

    $services->set(ApiRouteInfoResolver::class)
        ->args([
            service('router.default'),
        ]);

    $services->set(DataValidator::class)
        ->args([
            service('validator'),
        ]);

    $services->set(PsrHttpFactory::class)
        ->args([
            service(Psr17Factory::class),
            service(Psr17Factory::class),
            service(Psr17Factory::class),
            service(Psr17Factory::class),
        ]);

    $services->set(Psr17Factory::class);

    $services->set(HappyPathValidator::class)
        ->decorate('validator')
        ->args([
            service(HappyPathValidator::class . '.inner'),
        ]);

    $services->set(FeatureFlagController::class)
        ->public()
        ->args([
            service(FeatureFlagRegistry::class),
            service(CacheClearer::class),
        ]);
};
