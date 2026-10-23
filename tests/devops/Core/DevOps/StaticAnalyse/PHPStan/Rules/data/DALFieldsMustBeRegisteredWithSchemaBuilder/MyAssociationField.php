<?php

declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field\Field;

use Contena\Core\Framework\DataAbstractionLayer\Field\AssociationField;

/**
 * @internal
 */
class MyAssociationField extends AssociationField
{
    protected function getSerializerClass(): string
    {
        return self::class;
    }
}
