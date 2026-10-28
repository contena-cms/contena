<?php declare(strict_types=1);

namespace Contena\Core\Content\MeasurementSystem\Unit;

use Contena\Core\Framework\Log\Package;

#[Package('inventory')]
class ConvertedUnit
{
    public function __construct(public readonly float $value, public readonly string $unit)
    {
    }
}
