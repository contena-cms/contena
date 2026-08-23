<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\VersionDataPayloadFieldSerializer;

/**
 * @internal
 */
class VersionDataPayloadField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return VersionDataPayloadFieldSerializer::class;
    }
}
