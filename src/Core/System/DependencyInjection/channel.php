<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\System\User\Channel\AccountService;
use Contena\Core\System\User\Channel\RegisterRoute;
use Contena\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Contena\Core\Content\Seo\SeoUrlPlaceholderHandlerInterface;
use Contena\Core\Framework\Adapter\Cache\CacheInvalidator;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Twig\NamespaceHierarchy\NamespaceHierarchyBuilder;
use Contena\Core\Framework\Adapter\Twig\TemplateFinder;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Contena\Core\Framework\Api\Route\ApiRouteInfoResolver;
use Contena\Core\Framework\App\Context\Gateway\AppContextGateway;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandExecutor;
use Contena\Core\Framework\Gateway\Context\Command\Executor\ContextGatewayCommandValidator;
use Contena\Core\Framework\Gateway\Context\Command\Handler\AddUserMessageCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\ChangeAddressCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\ChangeCheckoutOptionsCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\ChangeCurrencyCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\ChangeLanguageCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\ChangeShippingLocationCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\LoginUserCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\RegisterUserCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Contena\Core\Framework\Gateway\Context\Channel\ContextGatewayRoute;
use Contena\Core\Framework\Log\ExceptionLogger;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\Channel\Aggregate\ChannelAnalytics\ChannelAnalyticsDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelCountry\ChannelCountryDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelCurrency\ChannelCurrencyDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelDomain\ChannelDomainDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelFile\ChannelFileDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelLanguage\ChannelLanguageDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelPaymentMethod\ChannelPaymentMethodDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelShippingMethod\ChannelShippingMethodDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelTranslation\ChannelTranslationDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelType\ChannelTypeDefinition;
use Contena\Core\System\Channel\Aggregate\ChannelTypeTranslation\ChannelTypeTranslationDefinition;
use Contena\Core\System\Channel\Api\StoreApiResponseListener;
use Contena\Core\System\Channel\Api\StructEncoder;
use Contena\Core\System\Channel\Capability\ChannelTypeCapabilityRegistry;
use Contena\Core\System\Channel\Context\BaseChannelContextFactory;
use Contena\Core\System\Channel\Context\CachedBaseChannelContextFactory;
use Contena\Core\System\Channel\Context\CachedChannelContextFactory;
use Contena\Core\System\Channel\Context\CartRestorer;
use Contena\Core\System\Channel\Context\Cleanup\CleanupChannelContextTask;
use Contena\Core\System\Channel\Context\Cleanup\CleanupChannelContextTaskHandler;
use Contena\Core\System\Channel\Context\ContextFactory;
use Contena\Core\System\Channel\Context\InvalidationRaceAwareCache;
use Contena\Core\System\Channel\Context\ChannelContextFactory;
use Contena\Core\System\Channel\Context\ChannelContextPersister;
use Contena\Core\System\Channel\Context\ChannelContextRequestRestorer;
use Contena\Core\System\Channel\Context\ChannelContextRestorer;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\Channel\Context\ChannelContextValueResolver;
use Contena\Core\System\Channel\Cookie\AnalyticsCookieCollectListener;
use Contena\Core\System\Channel\DataAbstractionLayer\ChannelIndexer;
use Contena\Core\System\Channel\Entity\DefinitionRegistryChain;
use Contena\Core\System\Channel\Entity\ChannelDefinitionInstanceRegistry;
use Contena\Core\System\Channel\File\Api\ChannelFileAdministrationReader;
use Contena\Core\System\Channel\File\Api\ChannelFileController;
use Contena\Core\System\Channel\File\Discovery\ChannelFileDiscovery;
use Contena\Core\System\Channel\File\Loader\ChannelFileConfigurationLoader;
use Contena\Core\System\Channel\File\Loader\ChannelFileLoader;
use Contena\Core\System\Channel\File\Rendering\ChannelFileRenderer;
use Contena\Core\System\Channel\File\Rendering\ChannelFileStoreApiMcpSubscriber;
use Contena\Core\System\Channel\File\Rendering\ChannelFileTemplateOverrideLoader;
use Contena\Core\System\Channel\File\ChannelFileCacheInvalidator;
use Contena\Core\System\Channel\File\ChannelFileNotFoundSubscriber;
use Contena\Core\System\Channel\File\ChannelFileRequestPathResolver;
use Contena\Core\System\Channel\File\ChannelFileTemplateResolver;
use Contena\Core\System\Channel\Channel\ContextRoute;
use Contena\Core\System\Channel\Channel\ContextSwitchRoute;
use Contena\Core\System\Channel\Channel\ChannelContextSwitcher;
use Contena\Core\System\Channel\Channel\StoreApiInfoController;
use Contena\Core\System\Channel\ChannelDefinition;
use Contena\Core\System\Channel\ChannelExceptionHandler;
use Contena\Core\System\Channel\StoreApiCustomFieldMapper;
use Contena\Core\System\Channel\Subscriber\ChannelMaintenanceIpAllowlistSyncSubscriber;
use Contena\Core\System\Channel\Subscriber\ChannelTypeValidator;
use Contena\Core\System\Channel\Telemetry\ChannelTypeResolver;
use Contena\Core\System\Channel\Validation\ChannelValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(ChannelDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(ChannelTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelCountryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelCurrencyDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelDomainDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(ChannelLanguageDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelPaymentMethodDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelShippingMethodDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelTypeDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelTypeTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(ChannelAnalyticsDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'channel_analytics']);

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

    $services->set(ChannelFileStoreApiMcpSubscriber::class)
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

    $services->set(ChannelContextPersister::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
            param('contena.api.store.context_lifetime'),
        ]);

    $services->set(ChannelContextRequestRestorer::class)
        ->args([
            service(ChannelContextService::class),
        ]);

    $services->set(ChannelContextFactory::class)
        ->public()
        ->args([
            service('user.repository'),
            service('user_group.repository'),
            service('user_address.repository'),
            service('payment_method.repository'),
            tagged_iterator('tax.rule_type_filter'),
            service('event_dispatcher'),
            service('currency_country_rounding.repository'),
            service(BaseChannelContextFactory::class),
        ]);

    $services->set(BaseChannelContextFactory::class)
        ->args([
            service('channel.repository'),
            service('user_group.repository'),
            service('country.repository'),
            service('tax.repository'),
            service('payment_method.repository'),
            service('shipping_method.repository'),
            service('country_state.repository'),
            service('currency_country_rounding.repository'),
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
            service(InvalidationRaceAwareCache::class),
        ]);

    $services->set(InvalidationRaceAwareCache::class)
        ->args([
            service('cache.object'),
        ]);

    $services->set(CachedChannelContextFactory::class)
        ->decorate(ChannelContextFactory::class, null, -1000)
        ->public()
        ->args([
            service(CachedChannelContextFactory::class . '.inner'),
            service(InvalidationRaceAwareCache::class),
        ]);

    $services->set(ChannelContextService::class)
        ->args([
            service(ChannelContextFactory::class),
            service(ChannelContextPersister::class),
            service('event_dispatcher'),
            service(RequestStack::class),
        ]);

    $services->set(ChannelContextRestorer::class)
        ->args([
            service(ChannelContextFactory::class),
            service('order.repository'),
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(CartRestorer::class)
        ->args([
            service(ChannelContextFactory::class),
            service(ChannelContextPersister::class),
            service('event_dispatcher'),
            service(RequestStack::class),
        ]);

    $services->set(StoreApiInfoController::class)
        ->public()
        ->args([
            service(DefinitionService::class),
            service('twig'),
            param('contena.security.csp_templates'),
            service(ApiRouteInfoResolver::class),
        ]);

    $services->set(ChannelContextSwitcher::class)
        ->args([
            service(ContextSwitchRoute::class),
        ])
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(ContextSwitchRoute::class)
        ->public()
        ->args([
            service(DataValidator::class),
            service(ChannelContextPersister::class),
            service('event_dispatcher'),
            service(ChannelContextService::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ContextRoute::class)
        ->public()
        ->args([
            service(ExtensionDispatcher::class),
        ]);

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

    $services->set(ChannelContextValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1000]);

    $services->set(ChannelExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(StoreApiResponseListener::class)
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

    $services->set(ChannelValidator::class)
        ->args([
            service(Connection::class),
            service(ChannelTypeCapabilityRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ChannelTypeCapabilityRegistry::class)
        ->args([
            tagged_iterator('contena.channel.type_capabilities'),
        ]);

    $services->set(ChannelTypeValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(AnalyticsCookieCollectListener::class)
        ->args([
            service('channel_analytics.repository'),
        ])
        ->tag('kernel.event_listener');

    $services->set(StoreApiCustomFieldMapper::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    // Context Gateway
    $services->set(ContextGatewayRoute::class)
        ->public()
        ->args([
            service(AppContextGateway::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ContextGatewayCommandValidator::class)
        ->args([
            service(ExceptionLogger::class),
        ]);

    $services->set(ContextGatewayCommandExecutor::class)
        ->args([
            service(ContextSwitchRoute::class),
            service(ContextGatewayCommandRegistry::class),
            service(ContextGatewayCommandValidator::class),
            service(ExceptionLogger::class),
            service(ChannelContextService::class),
        ]);

    $services->set(ContextGatewayCommandRegistry::class)
        ->args([
            tagged_iterator('contena.context.gateway.command'),
        ]);

    $services->set(AddUserMessageCommandHandler::class)
        ->tag('contena.context.gateway.command');

    $services->set(ChangeAddressCommandHandler::class)
        ->tag('contena.context.gateway.command');

    $services->set(ChangeCheckoutOptionsCommandHandler::class)
        ->args([
            service('payment_method.repository'),
            service('shipping_method.repository'),
        ])
        ->tag('contena.context.gateway.command');

    $services->set(ChangeCurrencyCommandHandler::class)
        ->args([
            service('currency.repository'),
        ])
        ->tag('contena.context.gateway.command');

    $services->set(ChangeLanguageCommandHandler::class)
        ->args([
            service('language.repository'),
        ])
        ->tag('contena.context.gateway.command');

    $services->set(ChangeShippingLocationCommandHandler::class)
        ->args([
            service('country.repository'),
            service('country_state.repository'),
        ])
        ->tag('contena.context.gateway.command');

    $services->set(LoginUserCommandHandler::class)
        ->args([
            service(AccountService::class),
        ])
        ->tag('contena.context.gateway.command');

    $services->set(RegisterUserCommandHandler::class)
        ->args([
            service(RegisterRoute::class),
        ])
        ->tag('contena.context.gateway.command');

    $services->set(ChannelMaintenanceIpAllowlistSyncSubscriber::class)
        ->tag('kernel.event_subscriber')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    // Telemetry: shared channel_type label resolver (cart calculation, order placed metrics)
    $services->set(ChannelTypeResolver::class);
};
