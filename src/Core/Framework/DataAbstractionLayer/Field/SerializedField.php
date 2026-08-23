<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\FieldSerializerInterface;

/**
 * @internal
 *
 * General field to support custom serializes in attribute entities, should never be used directly, but only over the #[Serialized] attribute.
 * If you use EntityDefinition classes you should add your own specific field for your custom serializer instead.
 */
class SerializedField extends Field implements StorageAware
{
    /**
     * @param class-string<FieldSerializerInterface> $serializer
     */
    public function __construct(
        private readonly string $storageName,
        string $propertyName,
        private readonly string $serializer
    ) {
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    protected function getSerializerClass(): string
    {
        return $this->serializer;
    }
}
