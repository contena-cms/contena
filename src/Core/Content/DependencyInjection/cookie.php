<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\Cookie\Channel\CookieConsentLogRoute;
use Contena\Core\Content\Cookie\Channel\CookieRoute;
use Contena\Core\Content\Cookie\ConsentLog\AbstractCookieConsentLogStorage;
use Contena\Core\Content\Cookie\ConsentLog\Command\ExportCookieConsentLogCommand;
use Contena\Core\Content\Cookie\ConsentLog\CookieConsentLogStorageRegistry;
use Contena\Core\Content\Cookie\ConsentLog\DatabaseCookieConsentLogStorage;
use Contena\Core\Content\Cookie\ConsentLog\FilesystemCookieConsentLogStorage;
use Contena\Core\Content\Cookie\ConsentLog\NullCookieConsentLogStorage;
use Contena\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTask;
use Contena\Core\Content\Cookie\ScheduledTask\CleanupCookieConsentLogTaskHandler;
use Contena\Core\Content\Cookie\Service\CookieProvider;
use Contena\Core\Framework\RateLimiter\RateLimiter;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(CookieProvider::class)
        ->args([
            service(EventDispatcherInterface::class),
            service('translator'),
            param('session.storage.options'),
            param('contena.cookie_consent.log_storage'),
            param('contena.cookie_consent.retention_days'),
        ]);

    $services->set(CookieRoute::class)
        ->public()
        ->args([
            service(CookieProvider::class),
        ]);

    // Consent log storages are selected by name via contena.cookie_consent.log_storage
    $services->set(DatabaseCookieConsentLogStorage::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.cookie_consent.log_storage', ['storage' => DatabaseCookieConsentLogStorage::NAME]);

    $services->set(FilesystemCookieConsentLogStorage::class)
        ->args([
            service('contena.filesystem.private'),
            param('contena.cookie_consent.filesystem_path'),
        ])
        ->tag('contena.cookie_consent.log_storage', ['storage' => FilesystemCookieConsentLogStorage::NAME]);

    $services->set(NullCookieConsentLogStorage::class)
        ->tag('contena.cookie_consent.log_storage', ['storage' => NullCookieConsentLogStorage::NAME]);

    $services->set(CookieConsentLogStorageRegistry::class)
        ->args([
            tagged_locator('contena.cookie_consent.log_storage', 'storage'),
            param('contena.cookie_consent.log_storage'),
        ]);

    $services->set(AbstractCookieConsentLogStorage::class)
        ->factory([service(CookieConsentLogStorageRegistry::class), 'getStorage']);

    $services->set(CookieConsentLogRoute::class)
        ->public()
        ->args([
            service(CookieRoute::class),
            service(AbstractCookieConsentLogStorage::class),
            service(ClockInterface::class),
            service(RateLimiter::class),
        ]);

    $services->set(CleanupCookieConsentLogTask::class)
        ->tag('contena.scheduled.task');

    $services->set(CleanupCookieConsentLogTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(AbstractCookieConsentLogStorage::class),
            service(ClockInterface::class),
            param('contena.cookie_consent.retention_days'),
        ])
        ->tag('messenger.message_handler');

    $services->set(ExportCookieConsentLogCommand::class)
        ->args([
            service(AbstractCookieConsentLogStorage::class),
        ])
        ->tag('console.command');
};
