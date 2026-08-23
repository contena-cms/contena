<?php declare(strict_types=1);

namespace Contena\Core\DevOps\DependencyInjection;

use Contena\Core\DevOps\System\Command\OpenApiValidationCommand;
use Contena\Core\DevOps\System\Command\SyncComposerVersionCommand;
use Contena\Core\Framework\Api\ApiDefinition\DefinitionService;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->defaults()
        ->autowire();

    $services->set(SyncComposerVersionCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(Filesystem::class),
        ])
        ->tag('console.command');

    $services->set(OpenApiValidationCommand::class)
        ->args([
            service(HttpClientInterface::class),
            service(DefinitionService::class),
        ])
        ->tag('console.command');
};
