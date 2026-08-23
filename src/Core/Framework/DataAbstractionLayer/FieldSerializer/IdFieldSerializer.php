<?php
declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Contena\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Contena\Core\Framework\DataAbstractionLayer\Field\StorageAware;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\Constraint\Uuid as UuidConstraint;

/**
 * @internal
 */
class IdFieldSerializer extends AbstractFieldSerializer
{
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        if (!$field->is(PrimaryKey::class)) {
            // we only need to save the reference to the entity into the context if it is a primary key
            return $data;
        }

        $key = $field->getPropertyName();
        if (!isset($data[$key])) {
            $data[$key] = Uuid::randomHex();
        }

        $parameters->getContext()->set($parameters->getDefinition()->getEntityName(), $key, $data[$key]);

        return $data;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof StorageAware) {
            throw DataAbstractionLayerException::invalidSerializerField(self::class, $field);
        }

        $value = $data->getValue();
        if ($value) {
            $this->validate([new UuidConstraint()], $data, $parameters->getPath());
        } elseif ($field->is(PrimaryKey::class) || $field->is(Required::class)) {
            $value = Uuid::randomHex();
        }

        if (!$value) {
            return yield $field->getStorageName() => null;
        }

        if (!\is_string($value)) {
            throw DataAbstractionLayerException::invalidIdFieldType($field, $value);
        }

        $parameters->getContext()->set($parameters->getDefinition()->getEntityName(), $data->getKey(), $value);
        yield $field->getStorageName() => Uuid::fromHexToBytes($value);
    }

    public function decode(Field $field, mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return Uuid::fromBytesToHex($value);
    }
}
