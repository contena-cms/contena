<?php declare(strict_types=1);

namespace Contena\Core\Service\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\App\ActiveAppsLoader;
use Contena\Core\Framework\App\AppExtractor;
use Contena\Core\Framework\App\AppStorage;
use Contena\Core\Framework\App\Command\UninstallAppCommand;
use Contena\Core\Framework\App\Lifecycle\AppLifecycle;
use Contena\Core\Framework\App\Lifecycle\AppManager;
use Contena\Core\Framework\App\Manifest\ManifestFactory;
use Contena\Core\Framework\App\Privileges\Privileges;
use Contena\Core\Framework\Notification\NotificationService;
use Contena\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Contena\Core\Framework\Store\Services\FirstRunWizardService;
use Contena\Core\Service\AllServiceInstaller;
use Contena\Core\Service\Api\PermissionController;
use Contena\Core\Service\Api\ServiceController;
use Contena\Core\Service\Command\Install;
use Contena\Core\Service\Command\UninstallAppCommandDecorator;
use Contena\Core\Service\LifecycleManager;
use Contena\Core\Service\MessageHandler\InstallServicesHandler;
use Contena\Core\Service\MessageHandler\LogConsentToRegistryHandler;
use Contena\Core\Service\MessageHandler\UpdateServiceHandler;
use Contena\Core\Service\Notification;
use Contena\Core\Service\Permission\PermissionsService;
use Contena\Core\Service\Requirement\RequirementsValidator;
use Contena\Core\Service\Requirement\ServiceConsentRequirement;
use Contena\Core\Service\Requirement\ServicesEnabledRequirement;
use Contena\Core\Service\Requirement\ContenaAccountRequirement;
use Contena\Core\Service\ScheduledTask\InstallServicesTask;
use Contena\Core\Service\ScheduledTask\InstallServicesTaskHandler;
use Contena\Core\Service\ServiceClientFactory;
use Contena\Core\Service\ServiceHookableEventDescriber;
use Contena\Core\Service\ServiceLifecycle;
use Contena\Core\Service\ServiceRegistry\Client;
use Contena\Core\Service\ServiceRegistry\PermissionLogger;
use Contena\Core\Service\ServiceRegistry\RegistryUrlProcessor;
use Contena\Core\Service\ServiceSourceResolver;
use Contena\Core\Service\ServiceStorage;
use Contena\Core\Service\ServiceWebhookPolicy;
use Contena\Core\Service\Subscriber\ExtensionCompatibilitiesResolvedSubscriber;
use Contena\Core\Service\Subscriber\InstalledExtensionsListingLoadedSubscriber;
use Contena\Core\Service\Subscriber\LicenseProviderSubscriber;
use Contena\Core\Service\Subscriber\PermissionsSubscriber;
use Contena\Core\Service\Subscriber\ServiceLifecycleSubscriber;
use Contena\Core\Service\Subscriber\ServiceWriteProtectionSubscriber;
use Contena\Core\Service\Subscriber\ContenaAccountSubscriber;
use Contena\Core\Service\Subscriber\SystemUpdateSubscriber;
use Contena\Core\Service\TemporaryDirectoryFactory;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $parameters = $containerConfigurator->parameters();
    $parameters->set('env(SERVICE_REGISTRY_URL)', ServiceExtension::DEFAULT_REGISTRY_URL);
    $parameters->set('env(ENABLE_SERVICES)', 'auto');

    $services = $containerConfigurator->services();

    $services->set(ServiceController::class)
        ->public()
        ->args([
            service(ServiceStorage::class),
            service('messenger.default_bus'),
            service(ServiceLifecycle::class),
            service(LifecycleManager::class),
            service(RequirementsValidator::class),
        ]);

    $services->set(PermissionController::class)
        ->public()
        ->args([
            service(PermissionsService::class),
        ]);

    $services->set(Install::class)
        ->args([
            service(LifecycleManager::class),
        ])
        ->tag('console.command');

    $services->set(RegistryUrlProcessor::class)
        ->args([
            ServiceExtension::DEFAULT_REGISTRY_URL,
            param('contena.service_registry.trusted_domains'),
        ])
        ->tag('container.env_var_processor');

    $services->set(Client::class)
        ->args([
            param('contena.service_registry.url'),
            env('APP_URL'),
            service('service_registry.http_client'),
        ])
        ->tag('kernel.reset', ['method' => 'reset']);

    $services->set(ServiceLifecycle::class)
        ->args([
            service(AppManager::class),
            service('app.repository'),
            service(ServiceStorage::class),
            service('logger'),
            service(ManifestFactory::class),
            service(ServiceSourceResolver::class),
            service('event_dispatcher'),
            service(RequirementsValidator::class),
            service(Client::class),
            service(ServiceClientFactory::class),
            service(Privileges::class),
        ]);

    $services->set(ServiceStorage::class)
        ->args([
            service('app.repository'),
        ]);

    $services->set(UninstallAppCommandDecorator::class)
        ->decorate(UninstallAppCommand::class)
        ->args([
            service(AppLifecycle::class),
            service(AppStorage::class),
            service(ServiceStorage::class),
            service(ServiceLifecycle::class),
            service(LifecycleManager::class),
        ])
        ->tag('console.command', ['command' => 'app:uninstall']);

    $services->set(ServiceClientFactory::class)
        ->args([
            service(HttpClientInterface::class),
            service(Client::class),
            param('kernel.contena_version'),
        ]);

    $services->set(AllServiceInstaller::class)
        ->args([
            service(Client::class),
            service(ServiceStorage::class),
            service(ServiceLifecycle::class),
            service('messenger.bus.default'),
            service('event_dispatcher'),
            service('logger'),
        ]);

    $services->set(InstallServicesTask::class)
        ->tag('contena.scheduled.task');

    $services->set(InstallServicesTaskHandler::class)
        ->args([
            service('scheduled_task.repository'),
            service('logger'),
            service(LifecycleManager::class),
            service(FirstRunWizardService::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(UpdateServiceHandler::class)
        ->args([
            service(ServiceLifecycle::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(InstallServicesHandler::class)
        ->args([
            service(LifecycleManager::class),
        ])
        ->tag('messenger.message_handler');

    $services->set(ServiceSourceResolver::class)
        ->args([
            service(Client::class),
            service(TemporaryDirectoryFactory::class),
            service(AppExtractor::class),
            service(Filesystem::class),
        ])
        ->tag('app.source_resolver', ['priority' => 100]);

    $services->set(ExtensionCompatibilitiesResolvedSubscriber::class)
        ->args([
            service(Client::class),
            service(AbstractExtensionDataProvider::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(InstalledExtensionsListingLoadedSubscriber::class)
        ->args([
            service('app.repository'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(SystemUpdateSubscriber::class)
        ->args([
            service(LifecycleManager::class),
            service('logger'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(TemporaryDirectoryFactory::class)
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(LicenseProviderSubscriber::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ServiceHookableEventDescriber::class)
        ->tag('contena.hookable_event.describer');

    $services->set(ServiceWebhookPolicy::class)
        ->args([service(ActiveAppsLoader::class)])
        ->tag('contena.webhook.policy');

    $services->set(PermissionsService::class)
        ->args([
            service(SystemConfigService::class),
            service('event_dispatcher'),
            service(PermissionLogger::class),
            service(ClockInterface::class),
        ]);

    $services->set(ServiceConsentRequirement::class)
        ->args([
            service(PermissionsService::class),
        ])
        ->tag('contena.service.requirement');

    $services->set(ServicesEnabledRequirement::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('contena.service.requirement');

    $services->set(ContenaAccountRequirement::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('contena.service.requirement');

    $services->set(RequirementsValidator::class)
        ->args([
            tagged_iterator('contena.service.requirement', null, 'getName'),
        ]);

    $services->set(LifecycleManager::class)
        ->args([
            env('ENABLE_SERVICES'),
            param('kernel.environment'),
            service(SystemConfigService::class),
            service(ServiceStorage::class),
            service(ServiceLifecycle::class),
            service(AllServiceInstaller::class),
            service(PermissionsService::class),
            service(Client::class),
        ]);

    $services->set(ServiceLifecycleSubscriber::class)
        ->args([
            service(Notification::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(PermissionsSubscriber::class)
        ->args([
            service(ServiceLifecycle::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ContenaAccountSubscriber::class)
        ->args([
            service(ServiceLifecycle::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(ServiceWriteProtectionSubscriber::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_subscriber');

    $services->set(Notification::class)
        ->args([
            service(NotificationService::class),
        ]);

    $services->set(PermissionLogger::class)
        ->args([
            service(Client::class),
            service('messenger.bus.default'),
            service(ShopIdProvider::class),
            service(SystemConfigService::class),
        ]);

    $services->set(LogConsentToRegistryHandler::class)
        ->args([
            service(PermissionLogger::class),
        ])
        ->tag('messenger.message_handler');
};
