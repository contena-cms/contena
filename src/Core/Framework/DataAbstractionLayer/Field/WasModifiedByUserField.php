<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\WasModifiedByUserFieldSerializer;

class WasModifiedByUserField extends BoolField
{
    public function __construct(string $storageName = 'was_modified_by_user', string $propertyName = 'wasModifiedByUser')
    {
        parent::__construct($storageName, $propertyName);
    }

    protected function getSerializerClass(): string
    {
        return WasModifiedByUserFieldSerializer::class;
    }
}
