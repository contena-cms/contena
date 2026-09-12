<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Category\Service\CategoryUrlGenerator;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Seo\SeoUrlPersister;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlLifecycleHandler;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlRouteLoader;
use Contena\Frontend\Framework\Seo\App\AppSeoUrlUpdateListener;
use Contena\Frontend\Framework\Seo\App\AppStaticSeoUrlSynchronizer;
use Contena\Frontend\Framework\Seo\FrontendCategoryUrlGenerator;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\SeoUrlUpdateListener;
use Contena\Frontend\Framework\Seo\SeoUrlRouteNameEnumProvider;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(AppSeoUrlRouteLoader::class)
        ->args([service(Connection::class), service(DefinitionInstanceRegistry::class), service('cache.object')])
        ->tag('contena.seo_url.route_loader')
        ->tag('kernel.event_subscriber')
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(AppStaticSeoUrlSynchronizer::class)
        ->args([service(AppSeoUrlRouteLoader::class), service('channel.repository'), service(SeoUrlPersister::class)]);

    $services->set(AppSeoUrlLifecycleHandler::class)
        ->args([service(AppSeoUrlRouteLoader::class), service(AppStaticSeoUrlSynchronizer::class), service(SeoUrlUpdater::class), service(DefinitionInstanceRegistry::class)])
        ->tag('contena.app_lifecycle.handler', ['priority' => -1500]);

    $services->set(AppSeoUrlUpdateListener::class)
        ->args([service(AppSeoUrlRouteLoader::class), service(AppStaticSeoUrlSynchronizer::class), service(SeoUrlUpdater::class)])
        ->tag('kernel.event_subscriber');

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
