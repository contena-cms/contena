<?php declare(strict_types=1);

namespace Contena\Core\Content\MeasurementSystem\Unit;

use Contena\Core\Content\MeasurementSystem\DataAbstractionLayer\MeasurementDisplayUnitEntity;
use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
abstract class AbstractMeasurementUnitProvider
{
    abstract public function getDecorated(): AbstractMeasurementUnitProvider;

    abstract public function getUnitInfo(string $unit): MeasurementDisplayUnitEntity;
}
