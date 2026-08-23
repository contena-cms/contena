<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Psr\Clock\ClockInterface;
use Contena\Core\System\User\Aggregate\UserAccessKey\UserAccessKeyDefinition;
use Contena\Core\System\User\Aggregate\UserConfig\UserConfigDefinition;
use Contena\Core\System\User\Aggregate\UserRecovery\UserRecoveryDefinition;
use Contena\Core\System\User\Aggregate\UserTag\UserTagDefinition;
use Contena\Core\System\User\Api\UserRecoveryController;
use Contena\Core\System\User\Api\UserValidationController;
use Contena\Core\System\User\Recovery\UserRecoveryService;
use Contena\Core\System\User\Service\UserValidationService;
use Contena\Core\System\User\UserDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(UserDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(UserConfigDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(UserAccessKeyDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(UserRecoveryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(UserTagDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(UserRecoveryService::class)
        ->args([
            service('user_recovery.repository'),
            service('user.repository'),
            service('router'),
            service('event_dispatcher'),
            service(ClockInterface::class),
        ]);

    $services->set(UserRecoveryController::class)
        ->public()
        ->args([
            service(UserRecoveryService::class),
            service('contena.rate_limiter'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(UserValidationService::class)
        ->args([
            service('user.repository'),
        ]);

    $services->set(UserValidationController::class)
        ->public()
        ->args([
            service(UserValidationService::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);
};
