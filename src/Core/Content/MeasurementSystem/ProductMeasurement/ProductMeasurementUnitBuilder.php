<?php declare(strict_types=1);

namespace Contena\Core\Content\MeasurementSystem\ProductMeasurement;

use Contena\Core\Content\MeasurementSystem\MeasurementUnits;
use Contena\Core\Content\MeasurementSystem\MeasurementUnitTypeEnum;
use Contena\Core\Content\MeasurementSystem\Unit\AbstractMeasurementUnitConverter;
use Contena\Core\Content\MeasurementSystem\Unit\ConvertedUnitSet;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * This class is responsible for building product internal measurement units
 */
#[Package('inventory')]
class ProductMeasurementUnitBuilder
{
    public function __construct(
        private readonly AbstractMeasurementUnitConverter $unitConverter
    ) {
    }

    public function buildFromContext(Entity $product, SalesChannelContext $context): ConvertedUnitSet
    {
        $lengthUnit = $context->getMeasurementSystem()->getUnit(MeasurementUnitTypeEnum::LENGTH->value);
        $weightUnit = $context->getMeasurementSystem()->getUnit(MeasurementUnitTypeEnum::WEIGHT->value);

        return $this->build($product, $lengthUnit, $weightUnit);
    }

    public function build(Entity $product, string $toLengthUnit, string $toWeightUnit): ConvertedUnitSet
    {
        $measurementUnit = new ConvertedUnitSet();

        foreach (ProductMeasurementEnum::DIMENSIONS_MAPPING as $dimension => $type) {
            if (!$product->has($dimension)) {
                continue;
            }

            $value = $product->get($dimension);

            if (!\is_float($value)) {
                continue;
            }

            $fromUnit = $type === MeasurementUnitTypeEnum::WEIGHT
                ? MeasurementUnits::DEFAULT_WEIGHT_UNIT
                : MeasurementUnits::DEFAULT_LENGTH_UNIT;

            $toUnit = $type === MeasurementUnitTypeEnum::WEIGHT
                ? $toWeightUnit
                : $toLengthUnit;

            $measurementUnit->addUnit($dimension, $this->unitConverter->convert($value, $fromUnit, $toUnit));
        }

        return $measurementUnit;
    }
}
