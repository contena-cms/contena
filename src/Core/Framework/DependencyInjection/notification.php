<?php declare(strict_types=1);

namespace Contena\Core\Framework\DependencyInjection;

use Contena\Core\Framework\Notification\Api\NotificationController;
use Contena\Core\Framework\Notification\NotificationDefinition;
use Contena\Core\Framework\Notification\NotificationService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(NotificationService::class)
        ->public()
        ->args([
            service('notification.repository'),
        ]);

    $services->set(NotificationController::class)
        ->public()
        ->args([
            service('contena.rate_limiter'),
            service(NotificationService::class),
        ])
        ->call('setContainer', [service('service_container')]);

    $services->set(NotificationDefinition::class)
        ->tag('contena.entity.definition');
};
