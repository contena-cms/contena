<?php
declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\FieldSerializer;

use Contena\Core\Framework\DataAbstractionLayer\Field\Field;
use Contena\Core\Framework\DataAbstractionLayer\Write\DataStack\KeyValuePair;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Contena\Core\Framework\DataAbstractionLayer\Write\WriteParameterBag;

/**
 * @internal
 */
interface FieldSerializerInterface
{
    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function normalize(Field $field, array $data, WriteParameterBag $parameters): array;

    /**
     * Encodes the provided DAL value to a persistable storage value
     *
     * @return \Generator<string, mixed>
     */
    public function encode(Field $field, EntityExistence $existence, KeyValuePair $data, WriteParameterBag $parameters): \Generator;

    /**
     * Decodes the storage value to the DAL value
     */
    public function decode(Field $field, mixed $value): mixed;
}
