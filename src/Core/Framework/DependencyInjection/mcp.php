<?php declare(strict_types=1);

use Contena\Core\Content\Flow\Api\FlowActionCollector;
use Contena\Core\Content\Media\Upload\MediaUploadService;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\Api\OAuth\ClientRepository;
use Contena\Core\Framework\Api\Serializer\JsonEntityEncoder;
use Contena\Core\Framework\App\AppSecretResolver;
use Contena\Core\Framework\App\Feature\AppFeatureStorage;
use Contena\Core\Framework\App\InstallationId\InstallationIdProvider;
use Contena\Core\Framework\App\Mcp\Feature\McpPromptFeatureDefinition;
use Contena\Core\Framework\App\Mcp\Feature\McpResourceFeatureDefinition;
use Contena\Core\Framework\App\Mcp\Feature\McpToolFeatureDefinition;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlistFilter;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlistListRequestHandler;
use Contena\Core\Framework\Mcp\AllowList\McpAllowlistProvider;
use Contena\Core\Framework\Mcp\Authentication\McpAuthenticationListener;
use Contena\Core\Framework\Mcp\Authentication\McpExceptionListener;
use Contena\Core\Framework\Mcp\Command\DebugMcpCommand;
use Contena\Core\Framework\Mcp\Context\ChannelApiMcpContextProvider;
use Contena\Core\Framework\Mcp\Context\McpContextProvider;
use Contena\Core\Framework\Mcp\Controller\ChannelApiMcpServerController;
use Contena\Core\Framework\Mcp\Controller\IntegrationMcpAllowlistController;
use Contena\Core\Framework\Mcp\Controller\McpServerController;
use Contena\Core\Framework\Mcp\Controller\McpToolListController;
use Contena\Core\Framework\Mcp\Controller\UserMcpAllowlistController;
use Contena\Core\Framework\Mcp\Http\McpHttpTransportFactory;
use Contena\Core\Framework\Mcp\Loader\AppMcpCapabilityExecutor;
use Contena\Core\Framework\Mcp\Loader\AppMcpPrivilegeProvider;
use Contena\Core\Framework\Mcp\Loader\AppMcpPromptLoader;
use Contena\Core\Framework\Mcp\Loader\AppMcpResourceLoader;
use Contena\Core\Framework\Mcp\Loader\AppMcpToolLoader;
use Contena\Core\Framework\Mcp\McpAllowedHostsProvider;
use Contena\Core\Framework\Mcp\McpCapabilityCatalog;
use Contena\Core\Framework\Mcp\McpToolsetRegistry;
use Contena\Core\Framework\Mcp\McpToolsetSessionStorage;
use Contena\Core\Framework\Mcp\Notification\AppMcpCapabilityDetector;
use Contena\Core\Framework\Mcp\Notification\AppMcpCapabilityLifecycleSubscriber;
use Contena\Core\Framework\Mcp\Notification\McpListChangedNotifier;
use Contena\Core\Framework\Mcp\Notification\McpSessionRegistry;
use Contena\Core\Framework\Mcp\Prompt\ContenaContextPrompt;
use Contena\Core\Framework\Mcp\RateLimit\McpRateLimiter;
use Contena\Core\Framework\Mcp\Resource\BusinessEventsResource;
use Contena\Core\Framework\Mcp\Resource\ChannelListResource;
use Contena\Core\Framework\Mcp\Resource\EntityListResource;
use Contena\Core\Framework\Mcp\Resource\ExtensionsResource;
use Contena\Core\Framework\Mcp\Resource\FlowActionsResource;
use Contena\Core\Framework\Mcp\Resource\LanguageListResource;
use Contena\Core\Framework\Mcp\Resource\StateMachineResource;
use Contena\Core\Framework\Mcp\Resource\ToolResultResource;
use Contena\Core\Framework\Mcp\ScheduledTask\McpToolsetSessionCleanupTask;
use Contena\Core\Framework\Mcp\ScheduledTask\McpToolsetSessionCleanupTaskHandler;
use Contena\Core\Framework\Mcp\Session\McpSessionCleanupSubscriber;
use Contena\Core\Framework\Mcp\Session\McpSessionIdValidator;
use Contena\Core\Framework\Mcp\Tool\EntityAggregateTool;
use Contena\Core\Framework\Mcp\Tool\EntityDeleteTool;
use Contena\Core\Framework\Mcp\Tool\EntityReadTool;
use Contena\Core\Framework\Mcp\Tool\EntitySchemaTool;
use Contena\Core\Framework\Mcp\Tool\EntitySearchTool;
use Contena\Core\Framework\Mcp\Tool\EntityUpsertTool;
use Contena\Core\Framework\Mcp\Tool\McpToolResponse;
use Contena\Core\Framework\Mcp\Tool\MediaUploadTool;
use Contena\Core\Framework\Mcp\Tool\Search\ToolSearch;
use Contena\Core\Framework\Mcp\Tool\SystemConfigReadTool;
use Contena\Core\Framework\Mcp\Tool\SystemConfigWriteTool;
use Contena\Core\Framework\Mcp\Tool\ToolSearchTool;
use Contena\Core\Framework\Mcp\Tool\ToolsetEnableTool;
use Contena\Core\Framework\Mcp\Tool\ToolsetsListTool;
use Contena\Core\Framework\Mcp\ToolResultCacheStorage;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Contena\Core\System\Channel\Mcp\Tool\ChannelApiContextTool;
use Contena\Core\System\Channel\Mcp\Tool\ChannelApiToolSearchTool;
use Contena\Core\System\Channel\Mcp\Tool\ChannelApiToolsetEnableTool;
use Contena\Core\System\Channel\Mcp\Tool\ChannelApiToolsetsListTool;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\Cache\Psr16Cache;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The bundle dropped the flat "mcp.pagination_limit" parameter when it gained multiple servers:
    // the limit became a per-server builder value. Keep one Contena-owned parameter as the single
    // source of truth — McpServerBuilderCompilerPass applies it to every server builder, and the
    // allowlist request handlers page with the same number.
    $container->parameters()->set('contena.mcp.pagination_limit', 50);

    $services->set('contena.mcp.session_registry_cache', Psr16Cache::class)
        ->args([service('cache.system')]);

    $services->set(McpSessionRegistry::class)
        ->args([
            service('contena.mcp.session_registry_cache'),
            'contena.mcp.active_session_ids',
            service('lock.factory'),
        ]);

    $services->set(McpListChangedNotifier::class)
        ->args([
            service('mcp.server.admin.session.store'),
            service(McpSessionRegistry::class),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpCapabilityDetector::class)
        ->args([service(AppFeatureStorage::class)]);

    $services->set(AppMcpCapabilityLifecycleSubscriber::class)
        ->args([
            service(AppMcpCapabilityDetector::class),
            service(McpListChangedNotifier::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(McpContextProvider::class)
        ->args([service('request_stack')]);

    $services->set(ChannelApiMcpContextProvider::class)
        ->args([service('request_stack')]);

    $services->set(McpAllowlistFilter::class);

    $services->set(McpAllowlistProvider::class)
        ->args([
            service(Connection::class),
            service('request_stack'),
            param('contena.mcp.tool_dependencies'),
        ]);

    $services->set(McpAllowlistListRequestHandler::class)
        ->args([
            service('mcp.server.admin.registry'),
            service(McpAllowlistProvider::class),
            param('contena.mcp.pagination_limit'),
            param('contena.mcp.advertised_tools'),
            service(McpToolsetRegistry::class),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('mcp.admin.request_handler');

    $services->set(McpAuthenticationListener::class)
        ->args([
            service(ClientRepository::class),
            service(RateLimiter::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(McpExceptionListener::class)
        ->tag('kernel.event_subscriber');

    $services->set(McpSessionIdValidator::class);

    $services->set(McpRateLimiter::class)
        ->args([service(RateLimiter::class)]);

    $services->set(McpAllowedHostsProvider::class)
        ->args([
            service(Connection::class),
            env('APP_URL'),
        ]);

    $services->set(McpHttpTransportFactory::class)
        ->args([
            service('mcp.psr_http_factory'),
            service('mcp.psr17_factory'),
            service('mcp.psr17_factory'),
            service('mcp.http_foundation_factory'),
            service(McpAllowedHostsProvider::class),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(McpServerController::class)
        ->public()
        ->args([
            service('mcp.server.admin'),
            service(McpHttpTransportFactory::class),
            service(McpRateLimiter::class),
            service(McpSessionIdValidator::class),
            service(McpAllowlistProvider::class),
            service('logger'),
            service(McpAllowlistFilter::class),
            service(McpSessionRegistry::class),
            service(McpListChangedNotifier::class),
        ])
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    // Store-api-scoped discovery stack: second instances of the scope-neutral discovery classes,
    // pointed at the channel-api registry/params and an isolated session registry (own cache) so
    // enabling an admin toolset never notifies channel-api sessions and vice versa.
    $services->set('mcp.channel_api.session_registry_cache', Psr16Cache::class)
        ->args([service('cache.system')]);

    // Distinct cache key from the Admin registry so the two endpoints' active-session populations
    // stay isolated even though both wrap the cache.system pool.
    $services->set('mcp.channel_api.session_registry', McpSessionRegistry::class)
        ->args([
            service('mcp.channel_api.session_registry_cache'),
            'contena.mcp.channel_api.active_session_ids',
            service('lock.factory'),
        ]);

    $services->set('mcp.channel_api.list_changed_notifier', McpListChangedNotifier::class)
        ->args([
            service('mcp.server.channel_api.session.store'),
            service('mcp.channel_api.session_registry'),
            service('logger'),
        ])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set('mcp.channel_api.capability_catalog', McpCapabilityCatalog::class)
        ->args([
            service('mcp.server.channel_api.registry'),
            service(AppMcpPrivilegeProvider::class),
            param('contena.channel_api_mcp.tool_dependencies'),
            param('contena.channel_api_mcp.tool_privileges'),
            param('contena.channel_api_mcp.tool_groups'),
        ]);

    $services->set('mcp.channel_api.toolset_registry', McpToolsetRegistry::class)
        ->args([service('mcp.channel_api.capability_catalog')]);

    $services->set('mcp.channel_api.list_request_handler', McpAllowlistListRequestHandler::class)
        ->args([
            service('mcp.server.channel_api.registry'),
            null,
            param('contena.mcp.pagination_limit'),
            param('contena.channel_api_mcp.advertised_tools'),
            service('mcp.channel_api.toolset_registry'),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('mcp.channel_api.request_handler');

    $services->set(ChannelApiMcpServerController::class)
        ->public()
        ->args([
            service('mcp.server.channel_api'),
            service(McpHttpTransportFactory::class),
            service(McpRateLimiter::class),
            service(McpSessionIdValidator::class),
            service('logger'),
            service('mcp.channel_api.session_registry'),
            service('mcp.channel_api.list_changed_notifier'),
        ])
        ->tag('controller.service_arguments')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpPrivilegeProvider::class)
        ->args([service(AppFeatureStorage::class), service('logger')])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(McpCapabilityCatalog::class)
        ->args([
            service('mcp.server.admin.registry'),
            service(AppMcpPrivilegeProvider::class),
            param('contena.mcp.tool_dependencies'),
            param('contena.mcp.tool_privileges'),
            param('contena.mcp.tool_groups'),
        ]);

    $services->set(McpToolsetRegistry::class)
        ->args([
            service(McpCapabilityCatalog::class),
            service(McpAllowlistProvider::class),
        ]);

    $services->set(McpToolListController::class)
        ->public()
        ->args([
            service('mcp.server.admin.builder'),
            service(McpCapabilityCatalog::class),
        ])
        ->tag('controller.service_arguments');

    $services->set(IntegrationMcpAllowlistController::class)
        ->public()
        ->args([service('integration.repository')])
        ->tag('controller.service_arguments');

    $services->set(UserMcpAllowlistController::class)
        ->public()
        ->args([service('user.repository')])
        ->tag('controller.service_arguments');

    $services->set(DebugMcpCommand::class)
        ->args([
            service('mcp.server.admin.builder'),
            service('mcp.server.admin.registry'),
            service(McpAllowlistProvider::class),
            service(McpCapabilityCatalog::class),
            service('mcp.server.channel_api.builder'),
            service('mcp.server.channel_api.registry'),
            service('mcp.channel_api.capability_catalog'),
            param('mcp.servers.unassigned'),
        ])
        ->tag('console.command');

    $services->set(ToolResultCacheStorage::class)
        ->args([service(Connection::class), service(ClockInterface::class)]);

    $services->set(ToolSearch::class);

    $services->set(McpToolsetSessionStorage::class)
        ->args([service(Connection::class), service(ClockInterface::class)]);

    $services->set(McpToolsetSessionCleanupTask::class)
        ->tag('contena.scheduled.task');

    $services->set(McpToolsetSessionCleanupTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(McpToolsetSessionStorage::class),
            service('mcp.server.admin.session.store'),
            service('mcp.server.channel_api.session.store'),
        ])
        ->tag('messenger.message_handler');

    $services->set(McpSessionCleanupSubscriber::class)
        ->args([
            service(ToolResultCacheStorage::class),
            service(McpToolsetSessionStorage::class),
            service(McpSessionRegistry::class),
            service('mcp.channel_api.session_registry'),
        ])
        ->tag('kernel.event_subscriber');

    $services->instanceof(McpToolResponse::class)
        ->call('setToolResultCache', [service(ToolResultCacheStorage::class), service('request_stack'), service('logger')])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    // Tools
    $services->set(ToolSearchTool::class)
        ->args([
            service('mcp.server.admin.registry'),
            service(ToolSearch::class),
            service(McpAllowlistProvider::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntitySchemaTool::class)
        ->args([service(DefinitionInstanceRegistry::class)])
        ->tag('mcp.tool');

    $services->set(EntitySearchTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(JsonEntityEncoder::class),
            service(AclCriteriaValidator::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityAggregateTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(AclCriteriaValidator::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityReadTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('api.request_criteria_builder'),
            service(McpContextProvider::class),
            service(JsonEntityEncoder::class),
            service(AclCriteriaValidator::class),
        ])
        ->tag('mcp.tool');

    $services->set(SystemConfigReadTool::class)
        ->args([
            service(SystemConfigService::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityUpsertTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
            service(Connection::class),
        ])
        ->tag('mcp.tool');

    $services->set(EntityDeleteTool::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(McpContextProvider::class),
            service(Connection::class),
        ])
        ->tag('mcp.tool');

    $services->set(SystemConfigWriteTool::class)
        ->args([
            service(SystemConfigService::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.tool');

    $services->set(MediaUploadTool::class)
        ->args([
            service(MediaUploadService::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.tool');

    $services->set(ChannelApiContextTool::class)
        ->args([service(ChannelApiMcpContextProvider::class)])
        ->tag('contena.channel_api_mcp.tool');

    $services->set(ChannelApiToolSearchTool::class)
        ->args([
            service('mcp.server.channel_api.registry'),
            service(ToolSearch::class),
            null,
        ])
        ->tag('contena.channel_api_mcp.tool');

    $services->set(ChannelApiToolsetsListTool::class)
        ->args([
            service('mcp.channel_api.toolset_registry'),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('contena.channel_api_mcp.tool');

    $services->set(ChannelApiToolsetEnableTool::class)
        ->args([
            service('mcp.channel_api.toolset_registry'),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('contena.channel_api_mcp.tool');

    $services->set(ToolsetsListTool::class)
        ->args([
            service(McpToolsetRegistry::class),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('mcp.tool');

    $services->set(ToolsetEnableTool::class)
        ->args([
            service(McpToolsetRegistry::class),
            service(McpToolsetSessionStorage::class),
            service('request_stack'),
        ])
        ->tag('mcp.tool');

    // Prompt
    $services->set(ContenaContextPrompt::class)
        ->tag('mcp.prompt');

    // Resources
    $services->set(EntityListResource::class)
        ->args([service(DefinitionInstanceRegistry::class)])
        ->tag('mcp.resource');

    $services->set(BusinessEventsResource::class)
        ->args([
            service(BusinessEventCollector::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.resource');

    $services->set(FlowActionsResource::class)
        ->args([
            service(FlowActionCollector::class),
            service(McpContextProvider::class),
        ])
        ->tag('mcp.resource');

    $services->set(ChannelListResource::class)
        ->args([service('channel.repository'), service(McpContextProvider::class)])
        ->tag('mcp.resource');

    $services->set(LanguageListResource::class)
        ->args([service('language.repository')])
        ->tag('mcp.resource');

    $services->set(StateMachineResource::class)
        ->args([service('state_machine.repository')])
        ->tag('mcp.resource');

    $services->set(ExtensionsResource::class)
        ->args([
            service(Connection::class),
            service('kernel'),
        ])
        ->tag('mcp.resource');

    $services->set(ToolResultResource::class)
        ->args([service(ToolResultCacheStorage::class)])
        ->tag('mcp.resource_template');

    // App MCP Tool pipeline
    $services->set(AppMcpCapabilityExecutor::class)
        ->args([
            service('contena.app_system.guzzle'),
            env('APP_URL'),
            service(InstallationIdProvider::class),
            param('contena.mcp.app_tool_timeout'),
            service('logger'),
            service('kernel'),
            service('request_stack'),
            service('router'),
            service(AppSecretResolver::class),
        ])
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpToolLoader::class)
        ->args([
            service(AppFeatureStorage::class),
            service(AppMcpCapabilityExecutor::class),
            service(LanguageLocaleCodeProvider::class),
            service('logger'),
            param('contena.mcp.allowed_tools'),
        ])
        ->tag('mcp.loader');

    $services->set(AppMcpPromptLoader::class)
        ->args([
            service(AppFeatureStorage::class),
            service(AppMcpCapabilityExecutor::class),
            service(LanguageLocaleCodeProvider::class),
            service('logger'),
        ])
        ->tag('mcp.loader')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(AppMcpResourceLoader::class)
        ->args([
            service(AppFeatureStorage::class),
            service(AppMcpCapabilityExecutor::class),
            service(LanguageLocaleCodeProvider::class),
            service('logger'),
        ])
        ->tag('mcp.loader')
        ->tag('monolog.logger', ['channel' => 'mcp']);

    $services->set(McpToolFeatureDefinition::class)->tag('contena.app_feature.definition');
    $services->set(McpPromptFeatureDefinition::class)->tag('contena.app_feature.definition');
    $services->set(McpResourceFeatureDefinition::class)->tag('contena.app_feature.definition');
};
