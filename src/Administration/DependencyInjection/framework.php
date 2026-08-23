<?php declare(strict_types=1);

namespace Contena\Administration\DependencyInjection;

use Psr\Clock\ClockInterface;
use Contena\Administration\Framework\Api\Subscriber\AdminInfoConfigBundlesSubscriber;
use Contena\Administration\Framework\Asset\AssetUploadListener;
use Contena\Administration\Framework\Routing\AdministrationRouteScope;
use Contena\Administration\Framework\Routing\KnownIps\KnownIpsCollector;
use Contena\Administration\Framework\Routing\NotFound\AdministrationNotFoundSubscriber;
use Contena\Administration\Framework\SystemCheck\AdministrationReadinessCheck;
use Contena\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouterInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(AdministrationNotFoundSubscriber::class)
        ->args([
            param('contena_administration.path_name'),
            service('service_container'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AdministrationRouteScope::class)
        ->args([
            param('contena_administration.path_name'),
        ])
        ->tag('contena.route_scope');

    $services->set(AdministrationReadinessCheck::class)
        ->args([
            service(RouterInterface::class),
            service(KernelInterface::class),
            service(ViteFileAccessorDecorator::class),
            service('filesystem'),
            service(ClockInterface::class),
        ])
        ->tag('contena.system_check');

    $services->set(KnownIpsCollector::class);

    $services->set(ViteFileAccessorDecorator::class)
        ->decorate('pentatrion_vite.file_accessor')
        ->args([
            param('pentatrion_vite.configs'),
            service('contena.asset.asset'),
            service('kernel'),
            service('filesystem'),
        ]);

    $services->set(AdminInfoConfigBundlesSubscriber::class)
        ->args([
            service('kernel'),
            service('router'),
            service('filesystem'),
            service(ViteFileAccessorDecorator::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(AssetUploadListener::class)
        ->tag('kernel.event_listener');
};
