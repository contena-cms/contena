<?php declare(strict_types=1);

namespace Contena\Core\Content\MeasurementSystem;

use Contena\Core\Framework\Log\Package;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('inventory')]
enum MeasurementUnitTypeEnum: string
{
    case LENGTH = 'length';
    case WEIGHT = 'weight';
}
