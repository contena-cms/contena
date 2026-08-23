<?php declare(strict_types=1);

namespace Contena\Core\DevOps\DependencyInjection;

use Doctrine\DBAL\Connection;
use Contena\Core\DevOps\System\Command\SystemDumpDatabaseCommand;
use Contena\Core\DevOps\System\Command\SystemRestoreDatabaseCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\MockHttpClient;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire();

    $services->set(SystemDumpDatabaseCommand::class)
        ->args([
            '%kernel.project_dir%/var/dumps',
            service(Connection::class),
        ])
        ->tag('console.command', ['command' => 'e2e:dump-db']);

    $services->set(SystemRestoreDatabaseCommand::class)
        ->args([
            '%kernel.project_dir%/var/dumps',
            service(Connection::class),
        ])
        ->tag('console.command', ['command' => 'e2e:restore-db'])
        ->tag('console.command', ['command' => 'e2e:cleanup']);

    $services->set('contena.usage_data.gateway.client', MockHttpClient::class)
        ->public();
};
