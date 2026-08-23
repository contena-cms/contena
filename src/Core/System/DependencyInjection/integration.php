<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Contena\Core\System\Integration\Aggregate\IntegrationRole\IntegrationRoleDefinition;
use Contena\Core\System\Integration\IntegrationDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(IntegrationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(IntegrationRoleDefinition::class)
        ->tag('contena.entity.definition');
};
