<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

use Contena\Core\Framework\DataAbstractionLayer\FieldSerializer\UpdatedAtFieldSerializer;

class UpdatedAtField extends DateTimeField
{
    public function __construct()
    {
        parent::__construct('updated_at', 'updatedAt');
    }

    protected function getSerializerClass(): string
    {
        return UpdatedAtFieldSerializer::class;
    }
}
