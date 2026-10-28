<?php declare(strict_types=1);

namespace Contena\Core\Content\DependencyInjection;

use Contena\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Contena\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementSystemEntity;
use Contena\Core\Content\MeasurementSystem\Field\MeasurementUnitsFieldSerializer;
use Contena\Core\Content\MeasurementSystem\ProductMeasurement\ProductMeasurementUnitBuilder;
use Contena\Core\Content\MeasurementSystem\TwigExtension\MeasurementConvertUnitTwigFilter;
use Contena\Core\Content\MeasurementSystem\Unit\MeasurementUnitConverter;
use Contena\Core\Content\MeasurementSystem\Unit\MeasurementUnitProvider;
use Contena\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $containerConfigurator): void {
    $services = $containerConfigurator->services();

    $services->set(MeasurementSystemEntity::class)
        ->tag('contena.entity');

    $services->set(MeasurementDisplayUnitEntity::class)
        ->tag('contena.entity');

    $services->set(MeasurementUnitsFieldSerializer::class)
        ->args([
            service('validator'),
            service(DefinitionInstanceRegistry::class),
        ])
        ->tag('contena.field_serializer');

    $services->set(MeasurementUnitProvider::class)
        ->args([
            service('measurement_display_unit.repository'),
        ]);

    $services->set(MeasurementUnitConverter::class)
        ->args([
            service(MeasurementUnitProvider::class),
        ]);

    $services->set(ProductMeasurementUnitBuilder::class)
        ->args([
            service(MeasurementUnitConverter::class),
        ]);

    $services->set(MeasurementConvertUnitTwigFilter::class)
        ->args([
            service(MeasurementUnitProvider::class),
            service(MeasurementUnitConverter::class),
        ])
        ->tag('twig.extension');
};
