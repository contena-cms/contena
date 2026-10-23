<?php declare(strict_types=1);

namespace Contena\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PropertyGroupOptionTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class PropertyGroupOptionTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getPropertyGroupOptionIds(): array
    {
        return $this->fmap(static fn (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) => $propertyGroupOptionTranslation->getPropertyGroupOptionId());
    }

    public function filterByPropertyGroupOptionId(string $id): self
    {
        return $this->filter(static fn (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) => $propertyGroupOptionTranslation->getPropertyGroupOptionId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) => $propertyGroupOptionTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (PropertyGroupOptionTranslationEntity $propertyGroupOptionTranslation) => $propertyGroupOptionTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'blog_group_option_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return PropertyGroupOptionTranslationEntity::class;
    }
}
