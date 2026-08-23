<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Contena\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Rule\AbstractRuleLoader;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\EventListener\Authentication\ChannelAuthenticationListener;
use Contena\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\ApiRequestContextResolver;
use Contena\Core\Framework\Routing\ChannelApiDomainResolver;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\Framework\Routing\ChannelRequestContextResolver;
use Contena\Core\Framework\Routing\MaintenanceModeResolver;
use Contena\Core\Framework\Routing\RouteScopeRegistry;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelCountry\ChannelCountryDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelLanguage\ChannelLanguageDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelTranslation\ChannelTranslationDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelTypeTranslation\ChannelTypeTranslationDefinition;
use Contena\Core\System\Channel\Api\ChannelApiResponseListener;
use Contena\Core\System\Channel\Api\StructEncoder;
use Contena\Core\System\Channel\Channel\ChannelApiInfoController;
use Contena\Core\System\Channel\Channel\ContextRoute;
use Contena\Core\System\Channel\Channel\ContextSwitchRoute;
use Contena\Core\System\Channel\ChannelApiCustomFieldMapper;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\Context\AbstractChannelContextFactory;
use Contena\Core\System\Channel\Context\BaseChannelContextFactory;
use Contena\Core\System\Channel\Context\CachedBaseChannelContextFactory;
use Contena\Core\System\Channel\Context\CachedChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextRequestRestorer;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextServiceInterface;
use Contena\Core\System\Channel\Context\ChannelContextValueResolver;
use Contena\Core\System\Channel\Context\ChannelRuleLoader;
use Contena\Core\System\Channel\Context\Cleanup\CleanupChannelContextTask;
use Contena\Core\System\Channel\Context\Cleanup\CleanupChannelContextTaskHandler;
use Contena\Core\System\Channel\Context\ContextFactory;
use Contena\Core\System\Channel\Cookie\AnalyticsCookieCollectListener;
use Contena\Core\System\Channel\DataAbstractionLayer\ChannelIndexer;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\Entity\DefinitionRegistryChain;
use Contena\Core\System\Channel\File\Api\ChannelFileAdministrationReader;
use Contena\Core\System\Channel\File\Api\ChannelFileController;
use Contena\Core\System\Channel\File\ChannelFileCacheInvalidator;
use Contena\Core\System\Channel\File\ChannelFileNotFoundSubscriber;
use Contena\Core\System\Channel\File\ChannelFileRequestPathResolver;
use Contena\Core\System\Channel\File\ChannelFileTemplateResolver;
use Contena\Core\System\Channel\File\Discovery\ChannelFileDiscovery;
use Contena\Core\System\Channel\File\Loader\ChannelFileConfigurationLoader;
use Contena\Core\System\Channel\File\Loader\ChannelFileLoader;
use Contena\Core\System\Channel\File\Rendering\ChannelFileChannelApiMcpSubscriber;
use Contena\Core\System\Channel\File\Rendering\ChannelFileRenderer;
use Contena\Core\System\Channel\File\Rendering\ChannelFileTemplateOverrideLoader;
use Contena\Core\System\Channel\Subscriber\ChannelTypeValidator;
use Contena\Core\System\Channel\Telemetry\ChannelTypeResolver;
use Contena\Core\System\Channel\Validation\ChannelValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ChannelDefinitionInstanceRegistry::class)
        ->public()
        ->args([
            '',
            service('service_container'),
            [],
            [],
        ]);

    $services->set(DefinitionRegistryChain::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(ChannelDefinitionInstanceRegistry::class),
        ]);

    $services->set(ChannelApiResponseListener::class)
        ->tag('kernel.event_subscriber')
        ->args([
            service(StructEncoder::class),
            service('event_dispatcher'),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service(MediaUrlPlaceholderHandlerInterface::class),
        ]);

    $services->set(StructEncoder::class)
        ->args([
            service(DefinitionRegistryChain::class),
            service('serializer'),
            service(Connection::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ChannelValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ChannelTypeValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(ChannelApiCustomFieldMapper::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ChannelTypeResolver::class);

    $services->set(AnalyticsCookieCollectListener::class)
        ->args([
            service('channel_analytics.repository'),
        ])
        ->tag('kernel.event_listener', ['event' => CookieGroupCollectEvent::class]);

    $services->set(ChannelFileDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelFileTemplateOverrideLoader::class)
        ->tag('twig.loader', ['priority' => 100])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ChannelFileDiscovery::class)
        ->public()
        ->args([
            service('twig.template_iterator'),
            service('cache.object'),
        ]);

    $services->set(ChannelFileConfigurationLoader::class)
        ->args([
            service('channel_file.repository'),
        ]);

    $services->set(ChannelFileTemplateResolver::class)
        ->args([
            service(TemplateFinder::class),
            service(NamespaceHierarchyBuilder::class),
            service('twig.loader'),
            service('event_dispatcher'),
        ]);

    $services->set(ChannelFileAdministrationReader::class)
        ->args([
            service(ChannelFileDiscovery::class),
            service(ChannelFileConfigurationLoader::class),
            service('twig'),
            service(ChannelFileTemplateResolver::class),
        ]);

    $services->set(ChannelFileRequestPathResolver::class);

    $services->set(ChannelFileRenderer::class)
        ->args([
            service('twig'),
            service(ChannelFileTemplateResolver::class),
            service(ChannelFileTemplateOverrideLoader::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service('channel.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ChannelFileChannelApiMcpSubscriber::class)
        ->args([
            service('router'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ChannelFileLoader::class)
        ->public()
        ->args([
            service(ChannelFileDiscovery::class),
            service(ChannelFileConfigurationLoader::class),
            service(ChannelFileRenderer::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(ChannelFileCacheInvalidator::class)
        ->args([
            service(CacheInvalidator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ChannelFileNotFoundSubscriber::class)
        ->args([
            service(ChannelFileLoader::class),
            service(ChannelFileRequestPathResolver::class),
            service(ChannelContextRequestRestorer::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ChannelFileController::class)
        ->public()
        ->args([
            service(ChannelFileAdministrationReader::class),
            service(ChannelFileLoader::class),
            service(ChannelContextFactory::class),
            service(ChannelFileRequestPathResolver::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(ChannelApiRouteScope::class)->tag('contena.route_scope');

    $services->set(ChannelApiDomainResolver::class)
        ->args([
            service(Connection::class),
            service(RouteScopeRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ChannelContextPersister::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
            param('contena.api.channel.context_lifetime'),
        ]);

    $services->set(ChannelContextRequestRestorer::class)
        ->args([
            service(ChannelContextService::class),
        ]);

    $services->set(ChannelContextFactory::class)
        ->public()
        ->args([
            service('member.repository'),
            service('member_group.repository'),
            service('event_dispatcher'),
            service(BaseChannelContextFactory::class),
        ]);

    $services->set(ChannelRuleLoader::class)
        ->args([service(AbstractRuleLoader::class)])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(BaseChannelContextFactory::class)
        ->args([
            service('channel.repository'),
            service('member_group.repository'),
            service('country.repository'),
            service(ContextFactory::class),
            service('language.repository'),
        ]);

    $services->set(ContextFactory::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(CachedBaseChannelContextFactory::class)
        ->decorate(BaseChannelContextFactory::class)
        ->args([
            service(CachedBaseChannelContextFactory::class . '.inner'),
            service('cache.object'),
        ]);

    $services->set(CachedChannelContextFactory::class)
        ->decorate(ChannelContextFactory::class, null, -1000)
        ->public()
        ->args([
            service(CachedChannelContextFactory::class . '.inner'),
            service('cache.object'),
        ]);

    $services->alias(AbstractChannelContextFactory::class, ChannelContextFactory::class);

    $services->set(ChannelContextService::class)
        ->args([
            service(ChannelContextFactory::class),
            service(ChannelRuleLoader::class),
            service(ChannelContextPersister::class),
            service('event_dispatcher'),
            service(RequestStack::class),
        ]);

    $services->alias(ChannelContextServiceInterface::class, ChannelContextService::class);

    $services->set(ChannelContextValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 200]);

    $services->set(ChannelIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('channel.repository'),
            service('event_dispatcher'),
            service(ManyToManyIdFieldUpdater::class),
        ])
        ->tag('contena.entity_indexer');

    $services->set(CleanupChannelContextTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CleanupChannelContextTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            param('contena.channel_context.expire_days'),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(ContextRoute::class)
        ->public();

    $services->set(ContextSwitchRoute::class)
        ->public()
        ->args([
            service(DataValidator::class),
            service(ChannelContextPersister::class),
            service('event_dispatcher'),
            service(ChannelContextService::class),
        ]);

    $services->set(ChannelApiInfoController::class)
        ->args([
            service(DefinitionService::class),
            service('twig'),
            param('contena.security.csp_templates'),
            service(ApiRouteInfoResolver::class),
        ])
        ->public();

    $services->set(ChannelRequestContextResolver::class)
        ->decorate(ApiRequestContextResolver::class)
        ->args([
            service(ChannelRequestContextResolver::class . '.inner'),
            service(ChannelContextServiceInterface::class),
            service('event_dispatcher'),
            service(RouteScopeRegistry::class),
        ]);

    $services->set(ChannelAuthenticationListener::class)
        ->args([
            service(Connection::class),
            service(RouteScopeRegistry::class),
            service(MaintenanceModeResolver::class),
        ])
        ->tag('kernel.event_subscriber');

    foreach ([
        ChannelDefinition::class,
        ChannelAnalyticsDefinition::class,
        ChannelTranslationDefinition::class,
        ChannelTypeDefinition::class,
        ChannelTypeTranslationDefinition::class,
        ChannelCountryDefinition::class,
        ChannelLanguageDefinition::class,
        ChannelDomainDefinition::class,
    ] as $definition) {
        $services->set($definition)->tag('contena.entity.definition');
    }
};
