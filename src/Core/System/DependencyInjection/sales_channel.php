<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Checkout\Customer\SalesChannel\AccountService;
use Contena\Core\Checkout\Customer\SalesChannel\RegisterRoute;
use Contena\Core\Content\Media\MediaUrlPlaceholderHandlerInterface;
use Contena\Core\Content\ProductStream\Service\ProductStreamBuilder;
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
use Contena\Core\Framework\Gateway\Context\Command\Handler\AddCustomerMessageCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\ChangeAddressCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\ChangeCheckoutOptionsCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\ChangeCurrencyCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\ChangeLanguageCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\ChangeShippingLocationCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\LoginCustomerCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Handler\RegisterCustomerCommandHandler;
use Contena\Core\Framework\Gateway\Context\Command\Registry\ContextGatewayCommandRegistry;
use Contena\Core\Framework\Gateway\Context\SalesChannel\ContextGatewayRoute;
use Contena\Core\Framework\Log\ExceptionLogger;
use Contena\Core\Framework\Validation\DataValidator;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelFile\SalesChannelFileDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Contena\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationDefinition;
use Contena\Core\System\SalesChannel\Api\StoreApiResponseListener;
use Contena\Core\System\SalesChannel\Api\StructEncoder;
use Contena\Core\System\SalesChannel\Capability\SalesChannelTypeCapabilityRegistry;
use Contena\Core\System\SalesChannel\Context\BaseSalesChannelContextFactory;
use Contena\Core\System\SalesChannel\Context\CachedBaseSalesChannelContextFactory;
use Contena\Core\System\SalesChannel\Context\CachedSalesChannelContextFactory;
use Contena\Core\System\SalesChannel\Context\CartRestorer;
use Contena\Core\System\SalesChannel\Context\Cleanup\CleanupSalesChannelContextTask;
use Contena\Core\System\SalesChannel\Context\Cleanup\CleanupSalesChannelContextTaskHandler;
use Contena\Core\System\SalesChannel\Context\ContextFactory;
use Contena\Core\System\SalesChannel\Context\InvalidationRaceAwareCache;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextPersister;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextRequestRestorer;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextRestorer;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextService;
use Contena\Core\System\SalesChannel\Context\SalesChannelContextValueResolver;
use Contena\Core\System\SalesChannel\Cookie\AnalyticsCookieCollectListener;
use Contena\Core\System\SalesChannel\DataAbstractionLayer\SalesChannelIndexer;
use Contena\Core\System\SalesChannel\Entity\DefinitionRegistryChain;
use Contena\Core\System\SalesChannel\Entity\SalesChannelDefinitionInstanceRegistry;
use Contena\Core\System\SalesChannel\File\Api\SalesChannelFileAdministrationReader;
use Contena\Core\System\SalesChannel\File\Api\SalesChannelFileController;
use Contena\Core\System\SalesChannel\File\Discovery\SalesChannelFileDiscovery;
use Contena\Core\System\SalesChannel\File\Loader\SalesChannelFileConfigurationLoader;
use Contena\Core\System\SalesChannel\File\Loader\SalesChannelFileLoader;
use Contena\Core\System\SalesChannel\File\Rendering\SalesChannelFileRenderer;
use Contena\Core\System\SalesChannel\File\Rendering\SalesChannelFileStoreApiMcpSubscriber;
use Contena\Core\System\SalesChannel\File\Rendering\SalesChannelFileTemplateOverrideLoader;
use Contena\Core\System\SalesChannel\File\SalesChannelFileCacheInvalidator;
use Contena\Core\System\SalesChannel\File\SalesChannelFileNotFoundSubscriber;
use Contena\Core\System\SalesChannel\File\SalesChannelFileRequestPathResolver;
use Contena\Core\System\SalesChannel\File\SalesChannelFileTemplateResolver;
use Contena\Core\System\SalesChannel\ProductAssignment\Api\SalesChannelProductAssignmentController;
use Contena\Core\System\SalesChannel\ProductAssignment\SalesChannelProductAssigner;
use Contena\Core\System\SalesChannel\SalesChannel\ContextRoute;
use Contena\Core\System\SalesChannel\SalesChannel\ContextSwitchRoute;
use Contena\Core\System\SalesChannel\SalesChannel\SalesChannelContextSwitcher;
use Contena\Core\System\SalesChannel\SalesChannel\StoreApiInfoController;
use Contena\Core\System\SalesChannel\SalesChannelDefinition;
use Contena\Core\System\SalesChannel\SalesChannelExceptionHandler;
use Contena\Core\System\SalesChannel\StoreApiCustomFieldMapper;
use Contena\Core\System\SalesChannel\Subscriber\SalesChannelMaintenanceIpAllowlistSyncSubscriber;
use Contena\Core\System\SalesChannel\Subscriber\SalesChannelTypeValidator;
use Contena\Core\System\SalesChannel\Telemetry\SalesChannelTypeResolver;
use Contena\Core\System\SalesChannel\Validation\SalesChannelValidator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpFoundation\RequestStack;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(SalesChannelDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(SalesChannelTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelCountryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelCurrencyDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelDomainDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(SalesChannelLanguageDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelPaymentMethodDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelShippingMethodDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelTypeDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelTypeTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelAnalyticsDefinition::class)
        ->tag('contena.entity.definition', ['entity' => 'sales_channel_analytics']);

    $services->set(SalesChannelFileDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelFileTemplateOverrideLoader::class)
        ->tag('twig.loader', ['priority' => 100])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(SalesChannelFileDiscovery::class)
        ->public()
        ->args([
            service('twig.template_iterator'),
            service('cache.object'),
        ]);

    $services->set(SalesChannelFileConfigurationLoader::class)
        ->args([
            service('sales_channel_file.repository'),
        ]);

    $services->set(SalesChannelFileTemplateResolver::class)
        ->args([
            service(TemplateFinder::class),
            service(NamespaceHierarchyBuilder::class),
            service('twig.loader'),
            service('event_dispatcher'),
        ]);

    $services->set(SalesChannelFileAdministrationReader::class)
        ->args([
            service(SalesChannelFileDiscovery::class),
            service(SalesChannelFileConfigurationLoader::class),
            service('twig'),
            service(SalesChannelFileTemplateResolver::class),
        ]);

    $services->set(SalesChannelFileRequestPathResolver::class);

    $services->set(SalesChannelFileRenderer::class)
        ->args([
            service('twig'),
            service(SalesChannelFileTemplateResolver::class),
            service(SalesChannelFileTemplateOverrideLoader::class),
            service(SeoUrlPlaceholderHandlerInterface::class),
            service('sales_channel.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(SalesChannelFileStoreApiMcpSubscriber::class)
        ->args([
            service('router'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelFileLoader::class)
        ->public()
        ->args([
            service(SalesChannelFileDiscovery::class),
            service(SalesChannelFileConfigurationLoader::class),
            service(SalesChannelFileRenderer::class),
            service(CacheTagCollector::class),
        ]);

    $services->set(SalesChannelFileCacheInvalidator::class)
        ->args([
            service(CacheInvalidator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelFileNotFoundSubscriber::class)
        ->args([
            service(SalesChannelFileLoader::class),
            service(SalesChannelFileRequestPathResolver::class),
            service(SalesChannelContextRequestRestorer::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelFileController::class)
        ->public()
        ->args([
            service(SalesChannelFileAdministrationReader::class),
            service(SalesChannelFileLoader::class),
            service(SalesChannelContextFactory::class),
            service(SalesChannelFileRequestPathResolver::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(SalesChannelProductAssigner::class)
        ->args([
            service('product.repository'),
            service('product_visibility.repository'),
            service('sales_channel.repository'),
            service(ProductStreamBuilder::class),
        ]);

    $services->set(SalesChannelProductAssignmentController::class)
        ->public()
        ->args([
            service(SalesChannelProductAssigner::class),
        ]);

    $services->set(SalesChannelContextPersister::class)
        ->args([
            service(Connection::class),
            service('event_dispatcher'),
            service(ClockInterface::class),
            param('contena.api.store.context_lifetime'),
        ]);

    $services->set(SalesChannelContextRequestRestorer::class)
        ->args([
            service(SalesChannelContextService::class),
        ]);

    $services->set(SalesChannelContextFactory::class)
        ->public()
        ->args([
            service('customer.repository'),
            service('customer_group.repository'),
            service('customer_address.repository'),
            service('payment_method.repository'),
            tagged_iterator('tax.rule_type_filter'),
            service('event_dispatcher'),
            service('currency_country_rounding.repository'),
            service(BaseSalesChannelContextFactory::class),
        ]);

    $services->set(BaseSalesChannelContextFactory::class)
        ->args([
            service('sales_channel.repository'),
            service('customer_group.repository'),
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

    $services->set(CachedBaseSalesChannelContextFactory::class)
        ->decorate(BaseSalesChannelContextFactory::class)
        ->args([
            service(CachedBaseSalesChannelContextFactory::class . '.inner'),
            service(InvalidationRaceAwareCache::class),
        ]);

    $services->set(InvalidationRaceAwareCache::class)
        ->args([
            service('cache.object'),
        ]);

    $services->set(CachedSalesChannelContextFactory::class)
        ->decorate(SalesChannelContextFactory::class, null, -1000)
        ->public()
        ->args([
            service(CachedSalesChannelContextFactory::class . '.inner'),
            service(InvalidationRaceAwareCache::class),
        ]);

    $services->set(SalesChannelContextService::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service(SalesChannelContextPersister::class),
            service('event_dispatcher'),
            service(RequestStack::class),
        ]);

    $services->set(SalesChannelContextRestorer::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service('order.repository'),
            service(Connection::class),
            service('event_dispatcher'),
        ]);

    $services->set(CartRestorer::class)
        ->args([
            service(SalesChannelContextFactory::class),
            service(SalesChannelContextPersister::class),
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

    $services->set(SalesChannelContextSwitcher::class)
        ->args([
            service(ContextSwitchRoute::class),
        ])
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    $services->set(ContextSwitchRoute::class)
        ->public()
        ->args([
            service(DataValidator::class),
            service(SalesChannelContextPersister::class),
            service('event_dispatcher'),
            service(SalesChannelContextService::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(ContextRoute::class)
        ->public()
        ->args([
            service(ExtensionDispatcher::class),
        ]);

    $services->set(SalesChannelDefinitionInstanceRegistry::class)
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
            service(SalesChannelDefinitionInstanceRegistry::class),
        ]);

    $services->set(SalesChannelContextValueResolver::class)
        ->tag('controller.argument_value_resolver', ['priority' => 1000]);

    $services->set(SalesChannelExceptionHandler::class)
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

    $services->set(SalesChannelIndexer::class)
        ->args([
            service(IteratorFactory::class),
            service('sales_channel.repository'),
            service('event_dispatcher'),
            service(ManyToManyIdFieldUpdater::class),
        ])
        ->tag('contena.entity_indexer');

    $services->set(CleanupSalesChannelContextTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CleanupSalesChannelContextTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(Connection::class),
            param('contena.sales_channel_context.expire_days'),
            service(ClockInterface::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(SalesChannelValidator::class)
        ->args([
            service(Connection::class),
            service(SalesChannelTypeCapabilityRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SalesChannelTypeCapabilityRegistry::class)
        ->args([
            tagged_iterator('contena.sales_channel.type_capabilities'),
        ]);

    $services->set(SalesChannelTypeValidator::class)
        ->tag('kernel.event_subscriber');

    $services->set(AnalyticsCookieCollectListener::class)
        ->args([
            service('sales_channel_analytics.repository'),
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
            service(SalesChannelContextService::class),
        ]);

    $services->set(ContextGatewayCommandRegistry::class)
        ->args([
            tagged_iterator('contena.context.gateway.command'),
        ]);

    $services->set(AddCustomerMessageCommandHandler::class)
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

    $services->set(LoginCustomerCommandHandler::class)
        ->args([
            service(AccountService::class),
        ])
        ->tag('contena.context.gateway.command');

    $services->set(RegisterCustomerCommandHandler::class)
        ->args([
            service(RegisterRoute::class),
        ])
        ->tag('contena.context.gateway.command');

    $services->set(SalesChannelMaintenanceIpAllowlistSyncSubscriber::class)
        ->tag('kernel.event_subscriber')
        ->tag('contena.inactiveFeature', ['flag' => 'v6.8.0.0']);

    // Telemetry: shared sales_channel_type label resolver (cart calculation, order placed metrics)
    $services->set(SalesChannelTypeResolver::class);
};
