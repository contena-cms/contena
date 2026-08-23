<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\IdFieldSerializer;

class IdField extends Field implements StorageAware
{
    public function __construct(
        protected string $storageName,
        string $propertyName
    ) {
        parent::__construct($propertyName);
    }

    public function getStorageName(): string
    {
        return $this->storageName;
    }

    public function getExtractPriority(): int
    {
        return 75;
    }

    protected function getSerializerClass(): string
    {
        return IdFieldSerializer::class;
    }
}
