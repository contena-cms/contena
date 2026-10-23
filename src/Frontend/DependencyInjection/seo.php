<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Category\Service\CategoryUrlGenerator;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Seo\SeoUrlGenerator;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Contena\Core\Framework\Api\Acl\AclCriteriaValidator;
use Contena\Core\Framework\App\Feature\AppFeatureStorage;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Routing\Validation\RouteBlocklistService;
use Contena\Core\System\Locale\LanguageLocaleCodeProvider;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlClaims;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlIndexer;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlLifecycleHandler;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlRouteLoader;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlRouteProvider;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlSynchronizer;
use Contena\Frontend\Framework\Seo\App\EntitySeoUrlAppFeatureDefinition;
use Contena\Frontend\Framework\Seo\App\SeoUrlAppFeatureDefinition;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\SeoUrlUpdateListener;
use Contena\Frontend\Framework\Seo\SeoUrlRouteNameEnumProvider;
use Contena\Frontend\Framework\Seo\FrontendCategoryUrlGenerator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Messenger\MessageBusInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(BlogPageSeoUrlRoute::class)
        ->args([
            service(BlogDefinition::class),
        ])
        ->tag('contena.seo_url.route');

    $services->set(NavigationPageSeoUrlRoute::class)
        ->args([
            service(CategoryDefinition::class),
            service(CategoryBreadcrumbBuilder::class),
        ])
        ->tag('contena.seo_url.route');

    $services->set(LandingPageSeoUrlRoute::class)
        ->args([
            service(LandingPageDefinition::class),
        ])
        ->tag('contena.seo_url.route');

    $services->set(SeoUrlUpdateListener::class)
        ->args([
            service(SeoUrlUpdater::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AppSeoUrlClaims::class)
        ->args([
            service(Connection::class),
        ]);

    $services->set(SeoUrlAppFeatureDefinition::class)
        ->args([
            service(Connection::class),
            service(RouteBlocklistService::class),
            service(AppSeoUrlClaims::class),
        ])
        ->tag('contena.app_feature.definition');

    $services->set(EntitySeoUrlAppFeatureDefinition::class)
        ->args([
            service('seo_url_template.repository'),
            service(AppSeoUrlClaims::class),
            service(DefinitionInstanceRegistry::class),
            service(SeoUrlGenerator::class),
            service(AclCriteriaValidator::class),
        ])
        ->tag('contena.app_feature.definition');

    $services->set(AppSeoUrlRouteProvider::class)
        ->args([
            service(AppFeatureStorage::class),
        ])
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AppSeoUrlRouteLoader::class)
        ->args([
            service(AppSeoUrlRouteProvider::class),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.seo_url.route_loader');

    $services->set(AppSeoUrlIndexer::class)
        ->args([
            service(AppSeoUrlRouteProvider::class),
            service(SeoUrlUpdater::class),
            service(MessageBusInterface::class),
            service(AppSeoUrlSynchronizer::class),
        ])
        ->tag('contena.entity_indexer');

    $services->set(AppSeoUrlSynchronizer::class)
        ->args([
            service(AppFeatureStorage::class),
            service('channel.repository'),
            service(SeoUrlPersister::class),
            service(LanguageLocaleCodeProvider::class),
            service('router.default'),
            service('request_stack'),
        ]);

    $services->set(AppSeoUrlLifecycleHandler::class)
        ->args([
            service(AppFeatureStorage::class),
            service(Connection::class),
            service('seo_url_template.repository'),
            service(MessageBusInterface::class),
            service(AppSeoUrlSynchronizer::class),
        ])
        ->tag('contena.app_lifecycle.handler', ['priority' => -1250])
        ->tag('kernel.event_subscriber');

    $services->set(SeoUrlRouteNameEnumProvider::class)
        ->args([
            service(SeoUrlRouteRegistry::class),
        ])
        ->tag('contena.api.enum_provider');

    $services->set(FrontendCategoryUrlGenerator::class)
        ->decorate(CategoryUrlGenerator::class)
        ->args([
            service(FrontendCategoryUrlGenerator::class . '.inner'),
            service('router'),
        ]);
};
