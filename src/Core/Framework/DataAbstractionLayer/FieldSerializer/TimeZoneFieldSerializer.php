<?php
declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\StringField;
use Contena\Core\Framework\DataAbstractionLayer\Field\TimeZoneField;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Timezone;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
class TimeZoneFieldSerializer extends AbstractFieldSerializer
{
    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof TimeZoneField) {
            throw DataAbstractionLayerException::invalidSerializerField(TimeZoneField::class, $field);
        }

        $this->validateIfNeeded($field, $existence, $data, $parameters);

        yield $field->getStorageName() => $data->getValue() !== null ? (string) $data->getValue() : null;
    }

    public function decode(Field $field, mixed $value): ?string
    {
        if ($value === null) {
            return $value;
        }

        return (string) $value;
    }

    /**
     * @param StringField $field
     *
     * @return Constraint[]
     */
    protected function getConstraints(Field $field): array
    {
        return [
            new Type('string'),
            new Timezone(),
            new NotBlank(),
        ];
    }
}
