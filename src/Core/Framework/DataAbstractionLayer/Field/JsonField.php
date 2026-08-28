<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\JsonFieldAccessorBuilder;
use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\JsonFieldSerializer;

class JsonField extends Field implements StorageAware
{
    /**
     * @param list<Field> $propertyMapping
     * @param array<mixed>|null $default
     */
    public function __construct(
        protected string $storageName,
        string $propertyName,
        protected array $propertyMapping = [],
        protected ?array $default = null
    ) {
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    /**
     * @return list<Field>
     */
    public function getPropertyMapping(): array
    {
        return $this->propertyMapping;
    }

    /**
     * Adds a nested field to the JSON property mapping.
     *
     * Use this from {@see \Contena\Core\Framework\DataAbstractionLayer\EntityExtension::modifyFields()} to extend an existing JSON schema.
     */
    public function addPropertyMapping(Field $field): static
    {
        $this->propertyMapping[] = $field;

        return $this;
    }

    /**
     * @return array<mixed>|null
     */
    public function getDefault(): ?array
    {
        return $this->default;
    }

    protected function getSerializerClass(): string
    {
        return JsonFieldSerializer::class;
    }

    protected function getAccessorBuilderClass(): ?string
    {
        return JsonFieldAccessorBuilder::class;
    }
}
