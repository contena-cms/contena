<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Content\Cookie\Channel\CookieConsentLogRoute;
use Contena\Core\Content\Cookie\Channel\CookieRoute;
use Contena\Core\Content\Cookie\CookieConsentConfigVersion\CookieConsentConfigVersionDefinition;
use Contena\Core\Content\Cookie\CookieConsentLog\CookieConsentLogDefinition;
use Contena\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTask;
use Contena\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTaskHandler;
use Contena\Core\Content\Cookie\Service\CookieProvider;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\Tenant\TenantScopeContextProvider;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CookieProvider::class)
        ->args([
            service(EventDispatcherInterface::class),
            service('translator'),
            param('session.storage.options'),
        ]);

    $services->set(CookieRoute::class)
        ->public()
        ->args([
            service(CookieProvider::class),
        ]);

    $services->set(CookieConsentLogDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CookieConsentConfigVersionDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(CookieConsentLogRoute::class)
        ->public()
        ->args([
            service(CookieRoute::class),
            service(Connection::class),
            service(EventDispatcherInterface::class),
            service(ClockInterface::class),
        ]);

    $services->set(CleanupCookieConsentLogTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CleanupCookieConsentLogTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(SystemConfigService::class),
            service(Connection::class),
            service(ClockInterface::class),
            service(TenantScopeContextProvider::class),
        ])
        ->tag('messenger.message_handler');
};
