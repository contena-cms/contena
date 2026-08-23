<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\Breadcrumb\Channel\BreadcrumbRoute;
use Contena\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbDataLoader;
use Contena\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbLoaderConfigSerializer;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(BreadcrumbRoute::class)
        ->public()
        ->args([
            service(CategoryBreadcrumbBuilder::class),
            service(CacheTagCollector::class),
        ]);

    // Content System
    $services->set(BreadcrumbDataLoader::class)
        ->args([
            service(BreadcrumbRoute::class),
        ])
        ->tag('content_system.data_loader');

    $services->set(BreadcrumbLoaderConfigSerializer::class)
        ->tag('content_system.config_serializer');
};
