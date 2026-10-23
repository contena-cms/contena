<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionDefinition;
use Contena\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationDefinition;
use Contena\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationDefinition;
use Contena\Core\Content\Property\PropertyGroupDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(PropertyGroupDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(PropertyGroupOptionDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(PropertyGroupOptionTranslationDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(PropertyGroupTranslationDefinition::class)
        ->tag('contena.entity.definition');
};
