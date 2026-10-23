<?php declare(strict_types=1);

namespace Contena\Core\Content\Property\Aggregate\PropertyGroupTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PropertyGroupTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class PropertyGroupTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getPropertyGroupIds(): array
    {
        return $this->fmap(static fn (PropertyGroupTranslationEntity $propertyGroupTranslation) => $propertyGroupTranslation->getPropertyGroupId());
    }

    public function filterByPropertyGroupId(string $id): self
    {
        return $this->filter(static fn (PropertyGroupTranslationEntity $propertyGroupTranslation) => $propertyGroupTranslation->getPropertyGroupId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (PropertyGroupTranslationEntity $propertyGroupTranslation) => $propertyGroupTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (PropertyGroupTranslationEntity $propertyGroupTranslation) => $propertyGroupTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'blog_group_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return PropertyGroupTranslationEntity::class;
    }
}
