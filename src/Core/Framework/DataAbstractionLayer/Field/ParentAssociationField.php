<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Field;

class ParentAssociationField extends ManyToOneAssociationField
{
    public function __construct(
        string $referenceClass,
        string $referenceField = 'id'
    ) {
        parent::__construct('parent', 'parent_id', $referenceClass, $referenceField);
    }
}
