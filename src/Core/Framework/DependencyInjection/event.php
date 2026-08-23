<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Framework\Event\BusinessEventCollector;
use Contena\Core\Framework\Event\BusinessEventRegistry;
use Contena\Core\Framework\Event\NestedEventDispatcher;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(BusinessEventRegistry::class)->public();
    $services->set(BusinessEventCollector::class)->args([
        service(BusinessEventRegistry::class),
        service('event_dispatcher'),
    ])->public();

    $services->set(NestedEventDispatcher::class)
        ->decorate('event_dispatcher')
        ->args([service(NestedEventDispatcher::class . '.inner')]);

    $services->set(ExtensionDispatcher::class)
        ->args([service('event_dispatcher')]);
};
