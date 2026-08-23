<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;

/**
 * Field for storing data requirements map (JSON to array<string, DataRequirement>).
 *
 * @internal
 */
class DataRequirementsField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return DataRequirementsFieldSerializer::class;
    }
}
