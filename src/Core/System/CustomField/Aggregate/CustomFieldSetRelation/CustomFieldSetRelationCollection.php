<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Aggregate\CustomFieldSetRelation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CustomFieldSetRelationEntity>
 */
class CustomFieldSetRelationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'custom_field_set_relation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomFieldSetRelationEntity::class;
    }
}
