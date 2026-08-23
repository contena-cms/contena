<?php declare(strict_types=1);

namespace Contena\Core\Content\Flow\DataAbstractionLayer\Field;

use Contena\Core\Content\Flow\DataAbstractionLayer\FieldSerializer\FlowTemplateConfigFieldSerializer;
use Contena\Core\Framework\DataAbstractionLayer\Field\JsonField;

/**
 * @internal
 */
class FlowTemplateConfigField extends JsonField
{
    protected function getSerializerClass(): string
    {
        return FlowTemplateConfigFieldSerializer::class;
    }
}
