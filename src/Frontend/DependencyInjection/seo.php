<?php declare(strict_types=1);

namespace Contena\Frontend\DependencyInjection;

use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Category\CategoryDefinition;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Content\Category\Service\CategoryUrlGenerator;
use Contena\Core\Content\LandingPage\LandingPageDefinition;
use Contena\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Contena\Core\Content\Seo\SeoUrlUpdater;
use Contena\Frontend\Framework\Seo\FrontendCategoryUrlGenerator;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\BlogPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\LandingPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\NavigationPageSeoUrlRoute;
use Contena\Frontend\Framework\Seo\SeoUrlRoute\SeoUrlUpdateListener;
use Contena\Frontend\Framework\Seo\SeoUrlRouteNameEnumProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

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
