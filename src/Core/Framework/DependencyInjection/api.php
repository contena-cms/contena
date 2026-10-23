<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Doctrine\DBAL\Connection;
use Lcobucci\JWT\Configuration as JWTConfiguration;
use League\OAuth2\Server\AuthorizationServer;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Clock\ClockInterface;
use Psr\Container\ContainerInterface;
use Contena\Core\Content\Flow\Api\FlowActionCollector;
use Contena\Core\Content\Media\Upload\MediaFileExtensionListProvider;
use Contena\Core\Content\Media\Upload\PresignedMediaUploadService;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\ApiDefinition\Generator\AllStoreApiSchemaMigrationScopeProvider;
use Contena\Core\Framework\Api\ApiDefinition\Generator\BundleSchemaPathCollection;
use Contena\Core\Framework\Api\ApiDefinition\Generator\CachedEntitySchemaGenerator;
use Contena\Core\Framework\Api\ApiDefinition\Generator\CoreStoreApiSchemaMigrationScopeProvider;
use Contena\Core\Framework\Api\ApiDefinition\Generator\EntitySchemaGenerator;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiDefinitionSchemaBuilder;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiPathBuilder;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi\OpenApiSchemaBuilder;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApi3Generator;
use Contena\Core\Framework\Api\ApiDefinition\Generator\OpenApiRouteDefaultsFilter;
use Contena\Core\Framework\Api\ApiDefinition\Generator\StoreApiGenerator;
use Contena\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationReporter;
use Contena\Core\Framework\Api\ApiDefinition\Generator\StoreApiSchemaMigrationScopeProviderInterface;
use Contena\Core\Framework\Api\Command\CreateIntegrationCommand;
use Contena\Core\Framework\Api\Command\DumpClassSchemaCommand;
use Contena\Core\Framework\Api\Command\DumpSchemaCommand;
use Contena\Core\Framework\Api\Command\StoreApiSchemaMigrationReportCommand;
use Contena\Core\Framework\Api\Context\ContextValueResolver;
use Contena\Core\Framework\Api\Controller\AccessKeyController;
use Contena\Core\Framework\Api\Controller\ApiController;
use Contena\Core\Framework\Api\Controller\AuthController;
use Contena\Core\Framework\Api\Controller\CacheController;
use Contena\Core\Framework\Api\Controller\CustomSnippetFormatController;
use Contena\Core\Framework\Api\Controller\FallbackController;
use Contena\Core\Framework\Api\Controller\FeatureFlagController;
use Contena\Core\Framework\Api\Controller\HealthCheckController;
use Contena\Core\Framework\Api\Controller\IndexingController;
use Contena\Core\Framework\Api\Controller\InfoController;
use Contena\Core\Framework\Api\Controller\IntegrationController;
use Contena\Core\Framework\Api\Controller\OAuthAuthorizeController;
use Contena\Core\Framework\Api\Controller\SyncController;
use Contena\Core\Framework\Api\Controller\AdminUserController;
use Contena\Core\Framework\Api\Cors\CoreCorsHeaderProvider;
use Contena\Core\Framework\Api\Cors\CorsHeaderProviderInterface;
use Contena\Core\Framework\Api\EventListener\Authentication\ApiAuthenticationListener;
use Contena\Core\Framework\Api\EventListener\Authentication\ChannelAuthenticationListener;
use Contena\Core\Framework\Api\EventListener\Authentication\AdminUserCredentialsChangedSubscriber;
use Contena\Core\Framework\Api\EventListener\CompressedCriteriaRequestListener;
use Contena\Core\Framework\Api\EventListener\CorsListener;
use Contena\Core\Framework\Api\EventListener\ExpectationSubscriber;
use Contena\Core\Framework\Api\EventListener\JsonRequestTransformerListener;
use Contena\Core\Framework\Api\EventListener\ResponseExceptionListener;
use Contena\Core\Framework\Api\EventListener\ResponseHeaderListener;
use Contena\Core\Framework\Api\OAuth\AccessTokenRepository;
use Contena\Core\Framework\Api\OAuth\AuthCodeRepository;
use Contena\Core\Framework\Api\OAuth\Client\PublicClientRegistry;
use Contena\Core\Framework\Api\OAuth\ClientRepository;
use Contena\Core\Framework\Api\OAuth\FakeCryptKey;
use Contena\Core\Framework\Api\OAuth\GrantTypeFactory;
use Contena\Core\Framework\Api\OAuth\JWTConfigurationFactory;
use Contena\Core\Framework\Api\OAuth\RefreshTokenRepository;
use Contena\Core\Framework\Api\OAuth\Scope\AdminScope;
use Contena\Core\Framework\Api\OAuth\Scope\AdminUserVerifiedScope;
use Contena\Core\Framework\Api\OAuth\Scope\WriteScope;
use Contena\Core\Framework\Api\OAuth\ScopeRepository;
use Contena\Core\Framework\Api\OAuth\SymfonyBearerTokenValidator;
use Contena\Core\Framework\Api\OAuth\AdminUserRepository;
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
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionValidator;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\Feature\FeatureFlagRegistry;
use Contena\Core\Framework\MessageQueue\Stats\StatsService;
use Contena\Core\Framework\Plugin\KernelPluginCollection;
use Contena\Core\Framework\Routing\MaintenanceModeResolver;
use Contena\Core\Framework\Routing\RequestTransformer;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\Framework\Routing\RouteScopeRegistry;
use Contena\Core\Framework\Sso\Config\LoginConfigService;
use Contena\Core\Framework\Sso\SsoService;
use Contena\Core\Framework\Sso\TokenService\ExternalTokenService;
use Contena\Core\Framework\Sso\AdminUserService\AdminUserService;
use Contena\Core\Framework\Store\InAppPurchase;
use Contena\Core\Framework\SystemCheck\SystemChecker;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\Framework\Validation\HappyPathValidator;
use Contena\Core\Maintenance\System\Service\AppUrlVerifier;
use Contena\Core\System\Channel\Api\StructEncoder;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(RequestTransformerInterface::class, RequestTransformer::class)
        ->public();

    $services->set(FallbackController::class)
        ->public()
        ->call('setContainer', [service('service_container')]);

    $services->set(CoreCorsHeaderProvider::class)
        ->tag(CorsHeaderProviderInterface::SERVICE_TAG, ['priority' => 1000]);

    $services->set(CorsListener::class)
        ->args([
            tagged_iterator(CorsHeaderProviderInterface::SERVICE_TAG),
        ])
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

    $services->set(StoreApiSchemaMigrationReportCommand::class)
        ->args([
            service(StoreApiSchemaMigrationReporter::class),
            service(ChannelDefinitionInstanceRegistry::class),
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
            service(StructEncoder::class),
        ]);

    $services->set(DefinitionService::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(ChannelDefinitionInstanceRegistry::class),
            service(StoreApiGenerator::class),
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

    $services->set(OpenApiRouteDefaultsFilter::class)
        ->args([
            service('router'),
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

    $services->set(StoreApiGenerator::class)
        ->args([
            service(OpenApiSchemaBuilder::class),
            service(OpenApiDefinitionSchemaBuilder::class),
            param('kernel.bundles_metadata'),
            service(BundleSchemaPathCollection::class),
            service(OpenApiRouteDefaultsFilter::class),
        ]);

    $services->set(CoreStoreApiSchemaMigrationScopeProvider::class)
        ->tag(StoreApiSchemaMigrationScopeProviderInterface::SERVICE_TAG);

    $services->set(AllStoreApiSchemaMigrationScopeProvider::class)
        ->args([
            service(BundleSchemaPathCollection::class),
        ])
        ->tag(StoreApiSchemaMigrationScopeProviderInterface::SERVICE_TAG);

    $services->set(StoreApiSchemaMigrationReporter::class)
        ->args([
            service(OpenApiDefinitionSchemaBuilder::class),
            tagged_iterator(StoreApiSchemaMigrationScopeProviderInterface::SERVICE_TAG),
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
            service(BusinessEventCollector::class),
            service('contena.increment.gateway.registry'),
            service(MigrationInfo::class),
            service(AppUrlVerifier::class),
            service(FlowActionCollector::class),
            service(SystemConfigService::class),
            service(ApiRouteInfoResolver::class),
            service(InAppPurchase::class),
            service(ShopIdProvider::class),
            service(StatsService::class),
            service('event_dispatcher'),
            service(PresignedMediaUploadService::class)->nullOnInvalid(),
            service(MediaFileExtensionListProvider::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(AuthController::class)
        ->public()
        ->args([
            service('contena.api.authorization_server'),
            service(PsrHttpFactory::class),
            service('contena.rate_limiter'),
            service(Connection::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(OAuthAuthorizeController::class)
        ->public()
        ->args([
            service('contena.api.authorization_server'),
            service(PsrHttpFactory::class),
            service(Psr17Factory::class),
            service(PublicClientRegistry::class),
            service('contena.rate_limiter'),
            service('router'),
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

    $services->set(PublicClientRegistry::class)
        ->args([
            param('contena.api.oauth_clients'),
        ]);

    $services->set(ClientRepository::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
            service(PublicClientRegistry::class),
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

    $services->set(AdminUserRepository::class)
        ->args([
            service(Connection::class),
            service(LoginConfigService::class),
        ]);

    $services->set(WriteScope::class)
        ->tag('contena.oauth.scope');

    $services->set(AdminScope::class)
        ->tag('contena.oauth.scope');

    $services->set(AdminUserVerifiedScope::class)
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

    $services->set(CompressedCriteriaRequestListener::class)
        ->args([
            service(CompressedCriteriaDecoder::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ExpectationSubscriber::class)
        ->args([
            param('kernel.contena_version'),
            param('kernel.plugin_infos'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ChannelAuthenticationListener::class)
        ->args([
            service(Connection::class),
            service(RouteScopeRegistry::class),
            service(MaintenanceModeResolver::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AuthCodeRepository::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(GrantTypeFactory::class)
        ->args([
            service(AdminUserRepository::class),
            service(RefreshTokenRepository::class),
            service(AuthCodeRepository::class),
            service(AdminUserService::class),
            service(ExternalTokenService::class),
            service(ClockInterface::class),
            param('contena.api.refresh_token_ttl'),
            param('contena.api.auth_code_ttl'),
        ]);

    $services->set(ApiAuthenticationListener::class)
        ->args([
            service(SymfonyBearerTokenValidator::class),
            service('contena.api.authorization_server'),
            service(GrantTypeFactory::class),
            service(RouteScopeRegistry::class),
            param('contena.api.access_token_ttl'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AdminUserCredentialsChangedSubscriber::class)
        ->args([
            service(RefreshTokenRepository::class),
            service(AuthCodeRepository::class),
            service(Connection::class),
            service(ClockInterface::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AdminUserController::class)
        ->public()
        ->args([
            service('admin_user.repository'),
            service('acl_user_role.repository'),
            service('acl_role.repository'),
            service('admin_user_access_key.repository'),
            service(AdminUserDefinition::class),
            service(SsoService::class),
            service(Connection::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(IntegrationController::class)
        ->public()
        ->args([
            service('integration.repository'),
            service(Connection::class),
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

    $services->set(CustomSnippetFormatController::class)
        ->public()
        ->args([
            service(KernelPluginCollection::class),
            service('twig'),
        ]);

    $services->set(FeatureFlagController::class)
        ->public()
        ->args([
            service(FeatureFlagRegistry::class),
            service(CacheClearer::class),
        ]);
};
