<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Aggregate\CategoryTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CategoryTranslationEntity>
 */
class CategoryTranslationCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getCategoryIds(): array
    {
        return $this->fmap(static fn (CategoryTranslationEntity $categoryTranslation) => $categoryTranslation->getCategoryId());
    }

    public function filterByCategoryId(string $id): self
    {
        return $this->filter(static fn (CategoryTranslationEntity $categoryTranslation) => $categoryTranslation->getCategoryId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getLanguageIds(): array
    {
        return $this->fmap(static fn (CategoryTranslationEntity $categoryTranslation) => $categoryTranslation->getLanguageId());
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(static fn (CategoryTranslationEntity $categoryTranslation) => $categoryTranslation->getLanguageId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'category_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return CategoryTranslationEntity::class;
    }
}
