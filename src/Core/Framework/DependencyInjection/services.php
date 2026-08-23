<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Cocur\Slugify\Bridge\Twig\SlugifyExtension;
use Cocur\Slugify\Slugify;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Cache\Http\CacheStore;
use Contena\Core\Framework\Adapter\Cache\Http\ChannelCacheKeySubscriber;
use Contena\Core\Framework\Adapter\Cache\Http\HttpCacheKeyGenerator;
use Contena\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Contena\Core\Framework\Adapter\Command\S3FilesystemVisibilityCommand;
use Contena\Core\Framework\Adapter\Kernel\EnvIntOrNullProcessor;
use Contena\Core\Framework\Adapter\Kernel\HttpCacheKernel;
use Contena\Core\Framework\Adapter\Kernel\HttpKernel;
use Contena\Core\Framework\Adapter\Redis\RedisConnectionProvider;
use Contena\Core\Framework\Adapter\Storage\AbstractKeyValueStorage;
use Contena\Core\Framework\Adapter\Storage\MySQLKeyValueStorage;
use Contena\Core\Framework\Adapter\Translation\ConstraintViolationTranslator;
use Contena\Core\Framework\Adapter\Translation\Translator;
use Contena\Core\Framework\Adapter\Twig\Extension\ConfigExtension;
use Contena\Core\Framework\Adapter\Twig\Extension\FeatureFlagExtension;
use Contena\Core\Framework\Adapter\Twig\Extension\InstanceOfExtension;
use Contena\Core\Framework\Adapter\Twig\Extension\MediaExtension;
use Contena\Core\Framework\Adapter\Twig\Extension\NodeExtension;
use Contena\Core\Framework\Adapter\Twig\Extension\PcreExtension;
use Contena\Core\Framework\Adapter\Twig\Extension\PhpSyntaxExtension;
use Contena\Core\Framework\Adapter\Twig\Extension\SwSanitizeTwigFilter;
use Contena\Core\Framework\Adapter\Twig\Extension\TwigFeaturesWithInheritanceExtension;
use Contena\Core\Framework\Adapter\Twig\Filter\EmailIdnTwigFilter;
use Contena\Core\Framework\Adapter\Twig\Filter\LeadingSpacesFilter;
use Contena\Core\Framework\Adapter\Twig\Filter\ReplaceRecursiveFilter;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\BundleHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\SecurityExtension;
use Contena\Core\Framework\Adapter\Twig\StringTemplateRenderer;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Adapter\Twig\TemplateIterator;
use Contena\Core\Framework\Adapter\Twig\TemplateScopeDetector;
use Contena\Core\Framework\Adapter\Twig\TwigVariableParserFactory;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Feature\Command\FeatureDisableCommand;
use Contena\Core\Framework\Feature\Command\FeatureDumpCommand;
use Contena\Core\Framework\Feature\Command\FeatureEnableCommand;
use Contena\Core\Framework\Feature\Command\FeatureListCommand;
use Contena\Core\Framework\Feature\FeatureFlagRegistry;
use Contena\Core\Framework\Log\ExceptionLogger;
use Contena\Core\Framework\Log\LogEntryDefinition;
use Contena\Core\Framework\Log\Monolog\DoctrineSQLHandler;
use Contena\Core\Framework\Log\Monolog\ErrorCodeLogLevelHandler;
use Contena\Core\Framework\Log\Monolog\ExcludeExceptionHandler;
use Contena\Core\Framework\Log\ScheduledTask\LogCleanupTask;
use Contena\Core\Framework\Log\ScheduledTask\LogCleanupTaskHandler;
use Contena\Core\Framework\Migration\Command\CreateMigrationCommand;
use Contena\Core\Framework\Migration\Command\MigrationCommand;
use Contena\Core\Framework\Migration\Command\MigrationDestructiveCommand;
use Contena\Core\Framework\Migration\Command\RefreshMigrationCommand;
use Contena\Core\Framework\Migration\IndexerQueuer;
use Contena\Core\Framework\Migration\MigrationCollectionLoader;
use Contena\Core\Framework\Migration\MigrationInfo;
use Contena\Core\Framework\Migration\MigrationRuntime;
use Contena\Core\Framework\Migration\MigrationSource;
use Contena\Core\Framework\Plugin\KernelPluginCollection;
use Contena\Core\Framework\Routing\Annotation\CriteriaValueResolver;
use Contena\Core\Framework\Routing\ApiRequestContextResolver;
use Contena\Core\Framework\Routing\ApiRouteScope;
use Contena\Core\Framework\Routing\ContextResolverListener;
use Contena\Core\Framework\Routing\CoreSubscriber;
use Contena\Core\Framework\Routing\MaintenanceModeResolver;
use Contena\Core\Framework\Routing\QueryDataBagResolver;
use Contena\Core\Framework\Routing\RequestDataBagResolver;
use Contena\Core\Framework\Routing\RequestTransformerInterface;
use Contena\Core\Framework\Routing\RouteEventSubscriber;
use Contena\Core\Framework\Routing\RouteParamsCleanupListener;
use Contena\Core\Framework\Routing\RouteScope;
use Contena\Core\Framework\Routing\RouteScopeListener;
use Contena\Core\Framework\Routing\RouteScopeRegistry;
use Contena\Core\Framework\Routing\SymfonyRouteScopeWhitelist;
use Contena\Core\Framework\Routing\Telemetry\AreaResolver;
use Contena\Core\Framework\Routing\Telemetry\DomainResolver;
use Contena\Core\Framework\Routing\Telemetry\HttpRequestMetricSubscriber;
use Contena\Core\Framework\Routing\Telemetry\OperationResolver;
use Contena\Core\Framework\Routing\Validation\Constraint\RouteNotBlockedValidator;
use Contena\Core\Framework\Routing\Validation\RouteBlocklistService;
use Contena\Core\Framework\Struct\Serializer\StructNormalizer;
use Contena\Core\Framework\Telemetry\Telemetry;
use Contena\Core\Framework\Util\Backtrace\BacktraceCollector;
use Contena\Core\Framework\Util\HtmlPurifierConfigProvider;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\Kernel;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\Snippet\Api\SnippetController;
use Contena\Core\System\Snippet\Api\TranslationController;
use Contena\Core\System\Snippet\Files\SnippetFileCollection;
use Contena\Core\System\Snippet\Files\SnippetFileCollectionFactory;
use Contena\Core\System\Snippet\Files\SnippetFileLoader;
use Contena\Core\System\Snippet\Filter\AddedFilter;
use Contena\Core\System\Snippet\Filter\AuthorFilter;
use Contena\Core\System\Snippet\Filter\EditedFilter;
use Contena\Core\System\Snippet\Filter\EmptySnippetFilter;
use Contena\Core\System\Snippet\Filter\NamespaceFilter;
use Contena\Core\System\Snippet\Filter\SnippetFilterFactory;
use Contena\Core\System\Snippet\Filter\TermFilter;
use Contena\Core\System\Snippet\Filter\TranslationKeyFilter;
use Contena\Core\System\Snippet\Service\TranslationLoader;
use Contena\Core\System\Snippet\Service\TranslationMetadataStore;
use Contena\Core\System\Snippet\Service\TranslationRemover;
use Contena\Core\System\Snippet\Service\TranslationUpdater;
use Contena\Core\System\Snippet\SnippetService;
use Contena\Core\System\Snippet\Struct\TranslationConfig;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Bridge\Twig\Extension\TranslationExtension;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\Runner\Symfony\HttpKernelRunner;
use Symfony\Component\Runtime\Runner\Symfony\ResponseRunner;
use Symfony\Component\Runtime\SymfonyRuntime;
use Twig\Extra\Intl\IntlExtension;
use Twig\Extra\String\StringExtension;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();

    $parameters->set('contena.slug.config', [
        'regexp' => '/([^A-Za-z0-9\.]|-)+/',
        'lowercase' => false,
    ]);

    // Populated by RouteScopeCompilerPass with all route prefixes from the registers RouteScopes
    $parameters->set('contena.routing.registered_api_prefixes', []);

    // Migration config
    $parameters->set('core.migration.directories', []);

    $parameters->set('contena.security.csp_templates', [
        'default' => "\nobject-src 'none';\nscript-src 'none';\nbase-uri 'self';\nframe-ancestors 'none';\n            ",
        'administration' => "\nobject-src 'none';\nscript-src 'strict-dynamic' 'nonce-%%nonce%%' 'unsafe-inline' 'unsafe-eval' https: http:;\nbase-uri 'self';\nframe-ancestors 'none';\n            ",
        'storefront' => '',
        'installer' => '',
    ]);

    $parameters->set('contena_http_cache_enabled_default', 1);
    $parameters->set('contena.http.cache.enabled', env('CONTENA_HTTP_CACHE_ENABLED')->default('contena_http_cache_enabled_default'));

    $containerConfigurator->extension('monolog', [
        'channels' => ['business_events'],
        'handlers' => [
            'business_event_handler_buffer' => [
                'type' => 'buffer',
                'handler' => 'business_event_handler',
                'channels' => ['business_events'],
            ],
            'business_event_handler' => [
                'type' => 'service',
                'id' => DoctrineSQLHandler::class,
                'channels' => ['business_events'],
            ],
        ],
    ]);

    $services = $containerConfigurator->services();

    // Database / Doctrine
    $services->set(Connection::class)
        ->public()
        ->factory([Kernel::class, 'getConnection']);

    $services->set(QueryDataBagResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1000]);

    $services->set(RequestDataBagResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1000]);

    // Cache
    $services->set('slugify', Slugify::class)
        ->args([
            param('contena.slug.config'),
        ]);

    // Migration
    $services->set(MigrationSource::class . '.core', MigrationSource::class)
        ->args([
            'core',
        ])
        ->tag('contena.migration_source');

    $services->set(MigrationSource::class . '.core.V6_8', MigrationSource::class)
        ->args([
            'core.V6_8',
        ])
        ->tag('contena.migration_source');

    $services->set(MigrationSource::class . '.null', MigrationSource::class)
        ->args([
            'null',
            [],
        ])
        ->tag('contena.migration_source');

    $services->set(MigrationRuntime::class)
        ->args([
            service(Connection::class),
            service('logger'),
        ]);

    $services->set(MigrationCollectionLoader::class)
        ->public()
        ->args([
            service(Connection::class),
            service(MigrationRuntime::class),
            service('logger'),
            tagged_iterator('contena.migration_source'),
        ]);

    $services->set(MigrationInfo::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(CreateMigrationCommand::class)
        ->args([
            service(KernelPluginCollection::class),
            param('kernel.contena_core_dir'),
            param('kernel.contena_version'),
        ])
        ->tag('console.command');

    $services->set(RefreshMigrationCommand::class)
        ->args([
            service('filesystem'),
            service(ClockInterface::class),
        ])
        ->tag('console.command');

    $services->set(MigrationCommand::class)
        ->args([
            service(MigrationCollectionLoader::class),
            service('cache.object'),
            param('kernel.contena_version'),
        ])
        ->tag('console.command');

    $services->set(MigrationDestructiveCommand::class)
        ->args([
            service(MigrationCollectionLoader::class),
            service('cache.object'),
            param('kernel.contena_version'),
        ])
        ->tag('console.command');

    $services->set(IndexerQueuer::class)
        ->public()
        ->args([
            service(Connection::class),
        ]);

    // Serializer
    $services->set(StructNormalizer::class)
        ->tag('serializer.normalizer');

    // Routing
    $services->set(ContextResolverListener::class)
        ->args([
            service(ApiRequestContextResolver::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CoreSubscriber::class)
        ->args([
            param('contena.security.csp_templates'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SymfonyRouteScopeWhitelist::class)
        ->tag('contena.route_scope_whitelist');

    $services->set(RouteScopeListener::class)
        ->args([
            service(RouteScopeRegistry::class),
            service('request_stack'),
            tagged_iterator('contena.route_scope_whitelist'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(RouteEventSubscriber::class)
        ->tag('kernel.event_subscriber')
        ->args([
            service('event_dispatcher'),
        ]);

    $services->set(MaintenanceModeResolver::class)
        ->args([
            service('event_dispatcher'),
        ]);

    // Telemetry: per-main-request HTTP metrics and their label resolvers
    $services->set(AreaResolver::class);

    $services->set(EntityGroupResolver::class);

    $services->set(DomainResolver::class)
        ->args([
            service(EntityGroupResolver::class),
        ]);

    $services->set(OperationResolver::class);

    $services->set(HttpRequestMetricSubscriber::class)
        ->args([
            service(Telemetry::class),
            service(AreaResolver::class),
            service(DomainResolver::class),
            service(OperationResolver::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('contena.telemetry.subscriber');

    $services->set(RouteBlocklistService::class)
        ->args([
            service('router'),
        ]);

    $services->set(RouteNotBlockedValidator::class)
        ->args([
            service(RouteBlocklistService::class),
        ])
        ->tag('validator.constraint_validator');

    $services->set(Translator::class)
        ->decorate('translator')
        ->args([
            service(Translator::class . '.inner'),
            service('request_stack'),
            service('cache.object'),
            service('translator.formatter'),
            service(Connection::class),
            service(LanguageLocaleCodeProvider::class),
            service(SnippetService::class),
            service(CacheTagCollector::class),
        ])
        ->tag('monolog.logger');

    $services->set(ConstraintViolationTranslator::class)
        ->args([
            service('translator'),
        ]);

    // Snippets
    $services->set(SnippetService::class)
        ->lazy()
        ->args([
            service(Connection::class),
            service(SnippetFileCollection::class),
            service('snippet.repository'),
            service('snippet_set.repository'),
            service(SnippetFilterFactory::class),
            service(ExtensionDispatcher::class),
            service('event_dispatcher'),
            service('contena.filesystem.translation'),
            service('filesystem'),
        ]);

    $services->set(SnippetController::class)
        ->public()
        ->args([
            service(SnippetService::class),
            service(SnippetFileCollection::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(TranslationController::class)
        ->public()
        ->args([
            service(TranslationConfig::class),
            service(TranslationMetadataStore::class),
            service(TranslationUpdater::class),
            service(TranslationRemover::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(SnippetFileLoader::class)
        ->args([
            service(KernelInterface::class),
            service(Connection::class),
            service(TranslationConfig::class),
            service(TranslationLoader::class),
            service('contena.filesystem.translation'),
        ]);

    $services->set(SnippetFileCollection::class)
        ->public()
        ->lazy()
        ->factory([service(SnippetFileCollectionFactory::class), 'createSnippetFileCollection']);

    $services->set(SnippetFileCollectionFactory::class)
        ->args([
            service(SnippetFileLoader::class),
        ]);

    $services->set(SnippetFilterFactory::class)
        ->public()
        ->args([
            tagged_iterator('contena.snippet.filter'),
        ]);

    // SnippetFilters
    $services->set(AuthorFilter::class)
        ->tag('contena.snippet.filter');

    $services->set(AddedFilter::class)
        ->tag('contena.snippet.filter');

    $services->set(EditedFilter::class)
        ->tag('contena.snippet.filter');

    $services->set(EmptySnippetFilter::class)
        ->tag('contena.snippet.filter');

    $services->set(NamespaceFilter::class)
        ->tag('contena.snippet.filter');

    $services->set(TermFilter::class)
        ->tag('contena.snippet.filter');

    $services->set(TranslationKeyFilter::class)
        ->tag('contena.snippet.filter');

    $services->set(TemplateFinder::class)
        ->public()
        ->args([
            service('twig'),
            service('twig.loader'),
            param('twig.cache'),
            service(NamespaceHierarchyBuilder::class),
            service(TemplateScopeDetector::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(NamespaceHierarchyBuilder::class)
        ->args([
            tagged_iterator('contena.twig.hierarchy_builder'),
        ]);

    $services->set(BundleHierarchyBuilder::class)
        ->args([
            service('kernel'),
            service(Connection::class),
        ])
        ->tag('contena.twig.hierarchy_builder', ['priority' => 1000]);

    $services->set(TemplateScopeDetector::class)
        ->args([
            service('request_stack'),
        ]);

    $services->set(NodeExtension::class)
        ->args([
            service(TemplateFinder::class),
            service(TemplateScopeDetector::class),
        ])
        ->tag('twig.extension');

    $services->set(PhpSyntaxExtension::class)
        ->tag('twig.extension')
        ->tag('contena.seo_url.twig.extension');

    $services->set(FeatureFlagExtension::class)
        ->tag('twig.extension');

    $services->set(ConfigExtension::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('twig.extension');

    $services->set('twig.extension.intl', IntlExtension::class)
        ->tag('twig.extension');

    $services->set('twig.extension.string', StringExtension::class)
        ->tag('twig.extension');

    $services->set('twig.extension.trans', TranslationExtension::class)
        ->args([
            service('translator'),
        ])
        ->tag('twig.extension')
        ->tag('contena.app_script.twig.extension');

    $services->set(PcreExtension::class)
        ->tag('twig.extension')
        ->tag('contena.app_script.twig.extension');

    $services->set(InstanceOfExtension::class)
        ->tag('twig.extension');

    $services->set(EmailIdnTwigFilter::class)
        ->tag('twig.extension');

    $services->set(LeadingSpacesFilter::class)
        ->tag('twig.extension');

    $services->set(SlugifyExtension::class)
        ->args([
            service('slugify'),
        ])
        ->tag('twig.extension')
        ->tag('contena.seo_url.twig.extension');

    $services->set(ReplaceRecursiveFilter::class)
        ->tag('twig.extension')
        ->tag('contena.app_script.twig.extension');

    $services->set(SecurityExtension::class)
        ->args([
            param('contena.twig.allowed_php_functions'),
        ])
        ->tag('twig.extension')
        ->tag('contena.seo_url.twig.extension')
        ->tag('contena.app_script.twig.extension');

    $services->set(StringTemplateRenderer::class)
        ->args([
            service('twig'),
            param('contena.cache.twig.string_template_renderer_cache_dir'),
        ]);

    $services->set(TemplateIterator::class)
        ->decorate('twig.template_iterator')
        ->public()
        ->args([
            service(TemplateIterator::class . '.inner'),
            param('kernel.bundles'),
            param('kernel.bundles_metadata'),
        ]);

    $services->set(TwigVariableParserFactory::class);

    $services->set(ApiRequestContextResolver::class)
        ->args([
            service(Connection::class),
            service(RouteScopeRegistry::class),
        ]);

    $services->set(RouteScope::class)
        ->tag('contena.route_scope');

    $services->set(ApiRouteScope::class)
        ->tag('contena.route_scope');

    $services->set(RouteScopeRegistry::class)
        ->args([
            tagged_iterator('contena.route_scope'),
        ]);

    // Logging
    $services->set(ExceptionLogger::class)
        ->args([
            param('kernel.environment'),
            param('contena.logger.enforce_throw_exception'),
            service('logger'),
        ]);

    $services->set(LogCleanupTask::class)
        ->tag('contena.scheduled.task');

    $services->set(LogCleanupTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(SystemConfigService::class),
            service(Connection::class),
            service(ClockInterface::class),
            service(TenantScopeContextProvider::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(DoctrineSQLHandler::class)
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(LogEntryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CriteriaValueResolver::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(RequestCriteriaBuilder::class),
        ])
        ->tag('controller.argument_value_resolver');

    $services->set(FeatureDumpCommand::class)
        ->args([
            service('kernel'),
            service(Filesystem::class),
        ])
        ->tag('console.command')
        ->tag('console.command', ['command' => 'administration:dump:features']);

    $services->set(FeatureDisableCommand::class)
        ->args([
            service(FeatureFlagRegistry::class),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(FeatureEnableCommand::class)
        ->args([
            service(FeatureFlagRegistry::class),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(FeatureListCommand::class)
        ->tag('console.command');

    $services->set(S3FilesystemVisibilityCommand::class)
        ->args([
            service('contena.filesystem.private'),
            service('contena.filesystem.public'),
            service('contena.filesystem.theme'),
            service('contena.filesystem.sitemap'),
            service('contena.filesystem.asset'),
        ])
        ->tag('console.command');

    $services->set(HtmlPurifierConfigProvider::class);

    $services->set(HtmlSanitizer::class)
        ->public()
        ->args([
            param('contena.html_sanitizer.cache_dir'),
            param('contena.html_sanitizer.cache_enabled'),
            param('contena.html_sanitizer.sets'),
            param('contena.html_sanitizer.fields'),
            param('contena.html_sanitizer.enabled'),
            service(HtmlPurifierConfigProvider::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ExcludeExceptionHandler::class)
        ->decorate('monolog.handler.main', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([
            service(ExcludeExceptionHandler::class . '.inner'),
            param('contena.logger.exclude_exception'),
        ]);

    $services->set(ErrorCodeLogLevelHandler::class)
        ->decorate('monolog.handler.main', null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
        ->args([
            service(ErrorCodeLogLevelHandler::class . '.inner'),
            param('contena.logger.error_code_log_levels'),
        ]);

    $services->set(RouteParamsCleanupListener::class)
        ->tag('kernel.event_listener');

    $services->set(RedisConnectionFactory::class)
        ->args([
            param('contena.cache.redis_prefix'),
        ]);

    $services->set(RedisConnectionProvider::class)
        ->args([
            '', // $serviceLocator will be set in the compiler pass
        ]);

    $services->set(AbstractKeyValueStorage::class, MySQLKeyValueStorage::class)
        ->public()
        ->args([
            service(Connection::class),
        ]);

    $services->set('http_kernel', HttpKernel::class)
        ->public()
        ->args([
            service('event_dispatcher'),
            service('controller_resolver'),
            service('request_stack'),
            service('argument_resolver'),
            service(RequestTransformerInterface::class),
        ])
        ->tag('container.hot_path')
        ->tag('container.preload', ['class' => HttpKernelRunner::class])
        ->tag('container.preload', ['class' => ResponseRunner::class])
        ->tag('container.preload', ['class' => SymfonyRuntime::class]);

    $services->set('http_kernel.cache', HttpCacheKernel::class)
        ->decorate('http_kernel')
        ->args([
            service('http_kernel.cache.inner'),
            service(CacheStore::class),
            service('esi'),
            [],
            service('event_dispatcher'),
            param('contena.http_cache.reverse_proxy.enabled'),
        ]);

    $services->set(CacheStore::class)
        ->public()
        ->args([
            service('cache.http'),
            service('event_dispatcher'),
            service(HttpCacheKeyGenerator::class),
            service(MaintenanceModeResolver::class),
            param('session.storage.options'),
            service(CacheTagCollector::class),
            param('contena.http_cache.soft_purge'),
            service('messenger.bus.default'),
            service(ClockInterface::class),
        ]);

    $services->set(HttpCacheKeyGenerator::class)
        ->args([
            param('kernel.cache.hash'),
            service('event_dispatcher'),
            param('contena.http_cache.ignored_url_parameters'),
        ]);

    $services->set(ChannelCacheKeySubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(BacktraceCollector::class);

    $services->set(EnvIntOrNullProcessor::class)
        ->tag('container.env_var_processor');
    $services->set(TwigFeaturesWithInheritanceExtension::class)
        ->args([
            service(TemplateFinder::class),
        ])
        ->tag('twig.extension');

    $services->set(MediaExtension::class)
        ->args([
            service('media.repository'),
        ])
        ->tag('twig.extension');

    $services->set(SwSanitizeTwigFilter::class)
        ->args([
            service(HtmlSanitizer::class),
        ])
        ->tag('twig.extension')
        ->tag('kernel.reset', ['method' => 'reset']);
};
