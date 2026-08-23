<?php declare(strict_types=1);

namespace Contena\Core\System\CustomField;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CustomFieldEntity>
 */
class CustomFieldCollection extends EntityCollection
{
    public function filterByType(string $type): self
    {
        return $this->filter(static fn (CustomFieldEntity $attribute) => $attribute->getType() === $type);
    }

    public function getApiAlias(): string
    {
        return 'custom_field_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomFieldEntity::class;
    }
}
