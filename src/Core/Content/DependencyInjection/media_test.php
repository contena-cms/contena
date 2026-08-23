<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\Media\File\FileUrlValidatorInterface;
use Contena\Core\Content\Test\Media\File\FileUrlValidatorStub;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(FileUrlValidatorInterface::class, FileUrlValidatorStub::class);
};
