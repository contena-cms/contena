<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Field\VersionDataPayloadField;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

/**
 * @internal
 */
class VersionDataPayloadFieldSerializer implements FieldSerializerInterface
{
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array
    {
        return $data;
    }

    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator
    {
        if (!$field instanceof VersionDataPayloadField) {
            throw DataAbstractionLayerException::invalidSerializerField(VersionDataPayloadField::class, $field);
        }

        yield $field->getStorageName() => $data->getValue();
    }

    public function decode(Field $field, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        return json_decode((string) $value, true, 512, \JSON_THROW_ON_ERROR);
    }
}
