<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;

/**
 * Field for storing element slots with recursive ContentElement tree (JSON to ElementSlots).
 *
 * @internal
 */
class ElementSlotsField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return ElementSlotsFieldSerializer::class;
    }
}
