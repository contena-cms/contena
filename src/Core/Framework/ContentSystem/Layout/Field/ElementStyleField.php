<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;

/**
 * Nested field for an element's universal style (JSON to ElementStyle). Composed into
 * ContentElementField; the registry-driven validation lives in ElementStyleFieldSerializer.
 *
 * @internal
 */
class ElementStyleField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return ElementStyleFieldSerializer::class;
    }
}
