<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;
use Contena\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class VersionFieldSerializer implements FieldSerializerInterface
{
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        $value = $data[$field->getPropertyName()] ?? null;
        if ($value === null) {
            $value = $parameters->getContext()->getContext()->getVersionId();
        }

        // write version id of current object to write context
        $parameters->getContext()->set($parameters->getDefinition()->getEntityName(), 'versionId', $value);

        $data[$field->getPropertyName()] = $value;

        return $data;
    }

    public function encode(
        Field $field,
        EntityExistence $existence,
        KeyValuePair $data,
        WriteParameterBag $parameters
    ): \Generator {
        if (!$field instanceof VersionField) {
            throw DataAbstractionLayerException::invalidSerializerField(VersionField::class, $field);
        }

        if ($data->getValue() === null) {
            $result = $this->normalize($field, [$field->getPropertyName() => $data->getValue()], $parameters);
            $data->setValue($result[$field->getPropertyName()]);
        }

        yield $field->getStorageName() => Uuid::fromHexToBytes($data->getValue());
    }

    public function decode(Field $field, mixed $value): ?string
    {
        try {
            return Uuid::fromBytesToHex($value);
        } catch (\Exception) {
            return null;
        }
    }
}
