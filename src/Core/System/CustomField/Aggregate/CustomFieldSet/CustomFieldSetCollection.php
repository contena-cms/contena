<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField\Aggregate\CustomFieldSet;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CustomFieldSetEntity>
 */
class CustomFieldSetCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'custom_field_set_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomFieldSetEntity::class;
    }
}
