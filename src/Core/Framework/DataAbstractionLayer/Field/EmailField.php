<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\EmailFieldSerializer;

/**
 * @codeCoverageIgnore
 */
class EmailField extends StringField
{
    protected function getSerializerClass(): string
    {
        return EmailFieldSerializer::class;
    }
}
