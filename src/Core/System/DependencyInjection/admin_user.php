<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Psr\Clock\ClockInterface;
use Contena\Core\System\Channel\Context\ChannelContextService;
use Contena\Core\System\AdminUser\Aggregate\AdminUserAccessKey\AdminUserAccessKeyDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserConfig\AdminUserConfigDefinition;
use Contena\Core\System\AdminUser\Aggregate\AdminUserRecovery\AdminUserRecoveryDefinition;
use Contena\Core\System\AdminUser\Api\AdminUserRecoveryController;
use Contena\Core\System\AdminUser\Api\AdminUserValidationController;
use Contena\Core\System\AdminUser\Recovery\AdminUserRecoveryService;
use Contena\Core\System\AdminUser\Service\AdminUserValidationService;
use Contena\Core\System\AdminUser\AdminUserDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(AdminUserDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AdminUserConfigDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AdminUserAccessKeyDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AdminUserRecoveryDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(AdminUserRecoveryService::class)
        ->args([
            service('admin_user_recovery.repository'),
            service('admin_user.repository'),
            service('router'),
            service('event_dispatcher'),
            service(ChannelContextService::class),
            service('channel.repository'),
            service(ClockInterface::class),
        ]);

    $services->set(AdminUserRecoveryController::class)
        ->public()
        ->args([
            service(AdminUserRecoveryService::class),
            service('contena.rate_limiter'),
            param('contena.api.admin_user_recovery.enabled'),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);

    $services->set(AdminUserValidationService::class)
        ->args([
            service('admin_user.repository'),
        ]);

    $services->set(AdminUserValidationController::class)
        ->public()
        ->args([
            service(AdminUserValidationService::class),
        ])
        ->call('setContainer', [
            service('service_container'),
        ]);
};
