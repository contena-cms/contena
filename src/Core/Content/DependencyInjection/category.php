<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Category\Aggregate\CategoryTag\CategoryTagDefinition;
use Contena\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\Cms\CategoryNameCmsElementResolver;
use Contena\Core\Content\Category\Cms\CategoryNavigationCmsElementResolver;
use Contena\Core\Content\Category\DataAbstractionLayer\CategoryBreadcrumbUpdater;
use Contena\Core\Content\Category\DataAbstractionLayer\CategoryIndexer;
use Contena\Core\Content\Category\DataAbstractionLayer\CategoryNonExistentExceptionHandler;
use Contena\Core\Content\Category\SalesChannel\CategoryListRoute;
use Contena\Core\Content\Category\SalesChannel\CategoryRoute;
use Contena\Core\Content\Category\SalesChannel\NavigationRoute;
use Contena\Core\Content\Category\SalesChannel\SalesChannelCategoryDefinition;
use Contena\Core\Content\Category\SalesChannel\TreeBuildingNavigationRoute;
use Contena\Core\Content\Category\Service\CachedDefaultCategoryLevelLoader;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Category\Service\CategoryUrlGenerator;
use Contena\Core\Content\Category\Service\DefaultCategoryLevelLoader;
use Contena\Core\Content\Category\Service\NavigationLoader;
use Contena\Core\Content\Category\Subscriber\CategorySubscriber;
use Contena\Core\Content\Category\Subscriber\CategoryTreeMovedSubscriber;
use Contena\Core\Content\Category\Tree\CategoryTreePathResolver;
use Contena\Core\Content\Category\Validation\EntryPointValidator;
use Contena\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Contena\Core\Content\Cms\Service\EntityCmsSlotConfigInheritanceBuilder;
use Contena\Core\Content\Seo\SeoUrlRoute\EntityRouteResolver;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Contena\Core\Framework\DataAbstractionLayer\Indexing\TreeUpdater;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Util\HtmlSanitizer;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CategoryDefinition::class)
        ->tag('contena.entity.definition')
        ->tag('contena.entity.hookable');

    $services->set(CategoryTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CategoryTagDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(SalesChannelCategoryDefinition::class)
        ->tag('contena.sales_channel.entity.definition');

    $services->set(NavigationLoader::class)
        ->args([
            service('event_dispatcher'),
            service(NavigationRoute::class),
        ]);

    $services->set(NavigationRoute::class)
        ->public()
        ->args([
            service(Connection::class),
            service('sales_channel.category.repository'),
            service(CacheTagCollector::class),
            service(CategoryTreePathResolver::class),
            service(DefaultCategoryLevelLoader::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(DefaultCategoryLevelLoader::class)
        ->args([
            service('sales_channel.category.repository'),
        ]);

    $services->set(CachedDefaultCategoryLevelLoader::class)
        ->decorate(DefaultCategoryLevelLoader::class)
        ->args([
            service('cache.object'),
            service('event_dispatcher'),
            service(CachedDefaultCategoryLevelLoader::class . '.inner'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategoryTreePathResolver::class);

    $services->set(TreeBuildingNavigationRoute::class)
        ->decorate(NavigationRoute::class, null, -2000)
        ->public()
        ->args([
            service(TreeBuildingNavigationRoute::class . '.inner'),
        ]);

    $services->set(CategoryRoute::class)
        ->public()
        ->args([
            service('sales_channel.category.repository'),
            service(SalesChannelCmsPageLoader::class),
            service(EntityCmsSlotConfigInheritanceBuilder::class),
            service(SalesChannelCategoryDefinition::class),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CategoryListRoute::class)
        ->public()
        ->args([
            service('sales_channel.category.repository'),
            service(ExtensionDispatcher::class),
        ]);

    $services->set(CategoryIndexer::class)
        ->args([
            service(Connection::class),
            service(IteratorFactory::class),
            service('category.repository'),
            service(ChildCountUpdater::class),
            service(TreeUpdater::class),
            service(CategoryBreadcrumbUpdater::class),
            service('event_dispatcher'),
            service('messenger.default_bus'),
        ])
        // Must run before ProductIndexer so ProductCategoryDenormalizer can include parent category paths.
        ->tag('contena.entity_indexer', ['priority' => 105]);

    $services->set(CategoryBreadcrumbUpdater::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(TreeUpdater::class)
        ->args([
            service(DefinitionInstanceRegistry::class),
            service(Connection::class),
        ]);

    $services->set(CategoryBreadcrumbBuilder::class)
        ->args([
            service('category.repository'),
            service('sales_channel.product.repository'),
            service(Connection::class),
            service(EntityRouteResolver::class),
        ]);

    $services->set(CategoryUrlGenerator::class)
        ->args([
            service(EntityRouteResolver::class),
        ]);

    $services->set(EntryPointValidator::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategorySubscriber::class)
        ->args([
            service(SystemConfigService::class),
            service(CategoryUrlGenerator::class),
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategoryTreeMovedSubscriber::class)
        ->args([
            service(EntityIndexerRegistry::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(CategoryNonExistentExceptionHandler::class)
        ->tag('contena.dal.exception_handler');

    $services->set(CategoryNavigationCmsElementResolver::class)
        ->args([
            service(NavigationLoader::class),
        ])
        ->tag('contena.cms.data_resolver');

    $services->set(CategoryNameCmsElementResolver::class)
        ->args([
            service(HtmlSanitizer::class),
        ])
        ->tag('contena.cms.data_resolver');
};
