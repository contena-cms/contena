<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\Breadcrumb\SalesChannel\BreadcrumbRoute;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(BreadcrumbRoute::class)
        ->public()
        ->args([
            service(CategoryBreadcrumbBuilder::class),
            service(CacheTagCollector::class),
            service(ExtensionDispatcher::class),
        ]);
    // Content System
    $services->set(Contena\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbDataLoader::class)
        ->args([
            service(Contena\Core\Content\Breadcrumb\SalesChannel\BreadcrumbRoute::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(Contena\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');
};
