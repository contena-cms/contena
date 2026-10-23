<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(UnitDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(UnitTranslationDefinition::class)
        ->tag('contena.entity.definition');
};
