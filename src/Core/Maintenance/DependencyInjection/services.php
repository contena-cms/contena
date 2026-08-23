<?php declare(strict_types=1);

namespace Contena\Core\Maintenance\DependencyInjection;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Contena\Core\Framework\Adapter\Cache\CacheClearer;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Contena\Core\Installer\Finish\SystemLocker;
use Contena\Core\Maintenance\Channel\Command\ChannelCreateCommand;
use Contena\Core\Maintenance\Channel\Command\ChannelListCommand;
use Contena\Core\Maintenance\Channel\Command\ChannelMaintenanceDisableCommand;
use Contena\Core\Maintenance\Channel\Command\ChannelMaintenanceEnableCommand;
use Contena\Core\Maintenance\Channel\Command\ChannelReplaceUrlCommand;
use Contena\Core\Maintenance\Channel\Command\ChannelUpdateDomainCommand;
use Contena\Core\Maintenance\Channel\Service\ChannelCreator;
use Contena\Core\Maintenance\System\Command\SystemGenerateAppSecretCommand;
use Contena\Core\Maintenance\System\Command\SystemInstallCommand;
use Contena\Core\Maintenance\System\Command\SystemIsInstalledCommand;
use Contena\Core\Maintenance\System\Command\SystemSetupCommand;
use Contena\Core\Maintenance\System\Command\SystemUpdateFinishCommand;
use Contena\Core\Maintenance\System\Command\SystemUpdatePrepareCommand;
use Contena\Core\Maintenance\System\Service\DatabaseConnectionFactory;
use Contena\Core\Maintenance\System\Service\SetupDatabaseAdapter;
use Contena\Core\Maintenance\User\Command\UserChangePasswordCommand;
use Contena\Core\Maintenance\User\Command\UserCreateCommand;
use Contena\Core\Maintenance\User\Command\UserListCommand;
use Contena\Core\Maintenance\User\Service\UserProvisioner;
use Contena\Core\System\NumberRange\ValueGenerator\AbstractNumberRangeValueGenerator;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Dotenv\Command\DotenvDumpCommand;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
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
        ])
        ->tag('console.command');

    $services->set(SystemIsInstalledCommand::class)
        ->args([service(Connection::class)])
        ->tag('console.command');

    $services->set(SystemGenerateAppSecretCommand::class)->tag('console.command');

    $services->set(SystemSetupCommand::class)
        ->args([param('kernel.project_dir'), service(DotenvDumpCommand::class)])
        ->tag('console.command');

    $services->set(DotenvDumpCommand::class)
        ->args([param('kernel.project_dir')])
        ->tag('console.command');

    $services->set(SystemUpdatePrepareCommand::class)
        ->args([service('service_container'), param('kernel.contena_version')])
        ->tag('console.command');

    $services->set(SystemUpdateFinishCommand::class)
        ->args([
            service('event_dispatcher'),
            service(SystemConfigService::class),
            param('kernel.contena_version'),
        ])
        ->tag('console.command');

    $services->set(ChannelUpdateDomainCommand::class)
        ->args([service('channel_domain.repository')])
        ->tag('console.command');

    $services->set(ChannelReplaceUrlCommand::class)
        ->args([service('channel_domain.repository')])
        ->tag('console.command');

    $services->set(ChannelCreateCommand::class)
        ->args([service(ChannelCreator::class)])
        ->tag('console.command');

    $services->set(ChannelCreator::class)
        ->public()
        ->args([
            service(DefinitionInstanceRegistry::class),
            service('channel.repository'),
            service('country.repository'),
            service('category.repository'),
        ]);

    $services->set(ChannelListCommand::class)
        ->args([service('channel.repository')])
        ->tag('console.command');

    $services->set(ChannelMaintenanceEnableCommand::class)
        ->args([service('channel.repository')])
        ->tag('console.command');

    $services->set(ChannelMaintenanceDisableCommand::class)
        ->args([service('channel.repository')])
        ->tag('console.command');

    $services->set(UserCreateCommand::class)
        ->args([service(UserProvisioner::class)])
        ->tag('console.command');

    $services->set(UserChangePasswordCommand::class)
        ->args([service('user.repository')])
        ->tag('console.command');

    $services->set(UserListCommand::class)
        ->args([service('user.repository')])
        ->tag('console.command');

    $services->set(UserProvisioner::class)
        ->public()
        ->args([
            service(Connection::class),
            service(ClockInterface::class),
            service(AbstractNumberRangeValueGenerator::class),
        ]);

    $services->set(SetupDatabaseAdapter::class);

    $services->set(SystemLocker::class)
        ->args([param('kernel.project_dir')]);
};
