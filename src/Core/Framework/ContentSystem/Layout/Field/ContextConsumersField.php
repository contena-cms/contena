<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;

/**
 * Field for storing context consumers map (JSON to array<string, ContextConsumer>).
 *
 * @internal
 */
class ContextConsumersField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return ContextConsumersFieldSerializer::class;
    }
}
