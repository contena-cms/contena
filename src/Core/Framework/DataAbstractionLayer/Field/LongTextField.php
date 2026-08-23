<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\LongTextFieldSerializer;

class LongTextField extends Field implements StorageAware
{
    public function __construct(
        private readonly string $storageName,
        string $propertyName
    ) {
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    protected function getSerializerClass(): string
    {
        return LongTextFieldSerializer::class;
    }
}
