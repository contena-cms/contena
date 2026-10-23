<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\DependencyInjection;

use Doctrine\DBAL\Connection;
use GuzzleHttp\Client;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Framework\Store\Services\AbstractExtensionDataProvider;
use Contena\Core\Framework\Store\Services\ExtensionLifecycleService;
use Contena\Core\Installer\Finish\SystemLocker;
use Contena\Core\Maintenance\Channel\Command\ChannelCreateCommand;
use Contena\Core\Maintenance\Channel\Command\ChannelFileEnableCommand;
use Contena\Core\Maintenance\Channel\Command\ChannelListCommand;
use Contena\Core\Maintenance\Channel\Command\ChannelMaintenanceDisableCommand;
use Contena\Core\Maintenance\Channel\Command\ChannelMaintenanceEnableCommand;
use Contena\Core\Maintenance\Channel\Command\ChannelReplaceUrlCommand;
use Contena\Core\Maintenance\Channel\Command\ChannelUpdateDomainCommand;
use Contena\Core\Maintenance\Channel\Service\ChannelCreator;
use Contena\Core\Maintenance\Staging\Command\SystemSetupStagingCommand;
use Contena\Core\Maintenance\Staging\Handler\StagingAppHandler;
use Contena\Core\Maintenance\Staging\Handler\StagingExtensionHandler;
use Contena\Core\Maintenance\Staging\Handler\StagingMailHandler;
use Contena\Core\Maintenance\Staging\Handler\StagingChannelHandler;
use Contena\Core\Maintenance\Staging\Handler\StagingSystemConfigHandler;
use Contena\Core\Maintenance\System\Command\SystemConfigureShopCommand;
use Contena\Core\Maintenance\System\Command\SystemGenerateAppSecretCommand;
use Contena\Core\Maintenance\System\Command\SystemInstallCommand;
use Contena\Core\Maintenance\System\Command\SystemIsInstalledCommand;
use Contena\Core\Maintenance\System\Command\SystemSetupCommand;
use Contena\Core\Maintenance\System\Command\SystemUpdateFinishCommand;
use Contena\Core\Maintenance\System\Command\SystemUpdatePrepareCommand;
use Contena\Core\Maintenance\System\Service\AppUrlVerifier;
use Contena\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Contena\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Contena\Core\Maintenance\System\Service\ShopConfigurator;
use Contena\Core\Maintenance\AdminUser\Command\AdminUserChangePasswordCommand;
use Contena\Core\Maintenance\AdminUser\Command\AdminUserCreateCommand;
use Contena\Core\Maintenance\AdminUser\Command\AdminUserListCommand;
use Contena\Core\Maintenance\AdminUser\Service\AdminUserProvisioner;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Dotenv\Command\DotenvDumpCommand;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Contena\Core\Framework\App\ShopId\ShopIdProvider;

use function Symfony\Component\DependencyInjection\Loader\Configurator\env;
use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $containerConfigurator->parameters()
        ->set('env(APP_URL_CHECK_DISABLED)', 'false');

    $services = $containerConfigurator->services();

    $services->set(DatabaseConnectionFactory::class);

    $services->set(SystemInstallCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(SetupDatabaseAdapter::class),
            service(DatabaseConnectionFactory::class),
            service(CacheClearer::class),
            service(SystemLocker::class),
            service(ClockInterface::class),
            service(EventDispatcherInterface::class),
        ])
        ->tag('console.command');

    $services->set(SystemIsInstalledCommand::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('console.command');

    $services->set(SystemGenerateAppSecretCommand::class)
        ->tag('console.command');

    $services->set(SystemSetupCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(DotenvDumpCommand::class),
        ])
        ->tag('console.command');

    $services->set(DotenvDumpCommand::class)
        ->args([
            param('kernel.project_dir'),
        ])
        ->tag('console.command');

    $services->set(SystemUpdatePrepareCommand::class)
        ->args([
            service('service_container'),
            param('kernel.contena_version'),
        ])
        ->tag('console.command');

    $services->set(SystemUpdateFinishCommand::class)
        ->args([
            service('event_dispatcher'),
            service(SystemConfigService::class),
            param('kernel.contena_version'),
        ])
        ->tag('console.command');

    $services->set(ChannelUpdateDomainCommand::class)
        ->args([
            service('channel_domain.repository'),
        ])
        ->tag('console.command');

    $services->set(ChannelReplaceUrlCommand::class)
        ->args([
            service('channel_domain.repository'),
        ])
        ->tag('console.command');

    $services->set(SystemConfigureShopCommand::class)
        ->args([
            service(ShopConfigurator::class),
            service(CacheClearer::class),
        ])
        ->tag('console.command');

    $services->set(AppUrlVerifier::class)
        ->args([
            service('contena.maintenance.client'),
            service(Connection::class),
            param('kernel.environment'),
            env('APP_URL_CHECK_DISABLED')->bool(),
        ]);

    $services->set('contena.maintenance.client', Client::class);

    $services->set(ShopConfigurator::class)
        ->args([
            service(Connection::class),
            service(EventDispatcherInterface::class),
            service(ClockInterface::class),
        ]);

    $services->set(ChannelCreateCommand::class)
        ->args([
            service(ChannelCreator::class),
        ])
        ->tag('console.command');

    $services->set(ChannelCreator::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('channel.repository'),
            service('payment_method.repository'),
            service('shipping_method.repository'),
            service('country.repository'),
            service('category.repository'),
        ]);

    $services->set(ChannelListCommand::class)
        ->args([
            service('channel.repository'),
        ])
        ->tag('console.command');

    $services->set(ChannelFileEnableCommand::class)
        ->args([
            service('channel.repository'),
            service('channel_file.repository'),
        ])
        ->tag('console.command');

    $services->set(ChannelMaintenanceEnableCommand::class)
        ->args([
            service('channel.repository'),
        ])
        ->tag('console.command');

    $services->set(ChannelMaintenanceDisableCommand::class)
        ->args([
            service('channel.repository'),
        ])
        ->tag('console.command');

    $services->set(AdminUserCreateCommand::class)
        ->args([
            service(AdminUserProvisioner::class),
        ])
        ->tag('console.command');

    $services->set(AdminUserChangePasswordCommand::class)
        ->args([
            service('admin_user.repository'),
        ])
        ->tag('console.command');

    $services->set(AdminUserListCommand::class)
        ->args([
            service('admin_user.repository'),
        ])
        ->tag('console.command');

    $services->set(AdminUserProvisioner::class)
        ->public()
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
        ]);

    $services->set(SetupDatabaseAdapter::class);

    $services->set(SystemLocker::class)
        ->args([
            param('kernel.project_dir'),
        ]);

    $services->set(SystemSetupStagingCommand::class)
        ->args([
            service('event_dispatcher'),
            service(SystemConfigService::class),
            param('contena.staging.mailing.disable_delivery'),
            param('contena.staging.channel.domain_rewrite'),
            param('contena.staging.extensions.disable'),
            param('contena.staging.system_config'),
        ])
        ->tag('console.command');

    $services->set(StagingAppHandler::class)
        ->args([
            service(Connection::class),
            service(ShopIdProvider::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(StagingMailHandler::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(StagingChannelHandler::class)
        ->args([
            service(Connection::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(StagingExtensionHandler::class)
        ->args([
            service(KernelInterface::class),
            service(AbstractExtensionDataProvider::class),
            service(ExtensionLifecycleService::class),
        ])
        ->tag('kernel.event_listener');

    $services->set(StagingSystemConfigHandler::class)
        ->args([
            service(SystemConfigService::class),
        ])
        ->tag('kernel.event_listener');
};
