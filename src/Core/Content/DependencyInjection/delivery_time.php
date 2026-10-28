<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\System\DeliveryTime\Aggregate\DeliveryTimeTranslation\DeliveryTimeTranslationDefinition;
use Contena\Core\System\DeliveryTime\DeliveryTimeDefinition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(DeliveryTimeDefinition::class)
        ->tag('contena.entity.definition');

    $services->set(DeliveryTimeTranslationDefinition::class)
        ->tag('contena.entity.definition');
};
