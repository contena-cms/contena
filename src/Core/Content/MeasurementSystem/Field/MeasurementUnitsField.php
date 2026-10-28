<?php declare(strict_types=1);

namespace Contena\Core\Content\MeasurementSystem\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\ObjectField;
use Contena\Core\Framework\Log\Package;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class MeasurementUnitsField extends ObjectField
{
    protected function getSerializerClass(): string
    {
        return MeasurementUnitsFieldSerializer::class;
    }
}
