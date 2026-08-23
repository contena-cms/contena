<?php declare(strict_types=1);

namespace Contena\Core\System\DependencyInjection;

use Contena\Core\System\Position\Aggregate\PositionTranslation\PositionTranslationDefinition;
use Contena\Core\System\Position\PositionDefinition;
use Contena\Core\System\User\Aggregate\UserPosition\UserPositionDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(PositionDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(PositionTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(UserPositionDefinition::class)
        ->tag('contena.entity.definition');
};
