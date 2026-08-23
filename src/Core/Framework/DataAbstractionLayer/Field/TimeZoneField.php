<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\TimeZoneFieldSerializer;

class TimeZoneField extends StringField
{
    protected function getSerializerClass(): string
    {
        return TimeZoneFieldSerializer::class;
    }
}
