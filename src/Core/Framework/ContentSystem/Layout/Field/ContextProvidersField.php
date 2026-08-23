<?php declare(strict_types=1);

namespace Contena\Core\Framework\ContentSystem\Layout\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;

/**
 * Field for storing context providers map (JSON to array<string, ContextProvider>).
 *
 * @internal
 */
class ContextProvidersField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return ContextProvidersFieldSerializer::class;
    }
}
