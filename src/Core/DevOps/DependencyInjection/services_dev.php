<?php declare(strict_types=1);

namespace Contena\Core\DevOps\DependencyInjection;

use Contena\Core\DevOps\Test\Command\MakeCoverageTestCommand;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Filesystem;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MakeCoverageTestCommand::class)
        ->args([
            param('kernel.project_dir'),
            service(Filesystem::class),
            service('kernel'),
        ])
        ->tag('console.command');
};
