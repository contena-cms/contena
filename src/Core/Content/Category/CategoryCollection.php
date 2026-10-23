<?php declare(strict_types=1);

namespace Contena\Core\Content\Category;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\Util\AfterSort;

/**
 * @extends EntityCollection<CategoryEntity>
 */
class CategoryCollection extends EntityCollection
{
    /**
     * @return array<string>
     */
    public function getParentIds(): array
    {
        return $this->fmap(static fn (CategoryEntity $category) => $category->getParentId());
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(static fn (CategoryEntity $category) => $category->getParentId() === $id);
    }

    /**
     * @return array<string>
     */
    public function getMediaIds(): array
    {
        return $this->fmap(static fn (CategoryEntity $category) => $category->getMediaId());
    }

    public function filterByMediaId(string $id): self
    {
        return $this->filter(static fn (CategoryEntity $category) => $category->getMediaId() === $id);
    }

    public function sortByPosition(): self
    {
        $this->elements = AfterSort::sort($this->elements, 'afterCategoryId');

        return $this;
    }

    public function sortByName(): self
    {
        $this->sort(static fn (CategoryEntity $a, CategoryEntity $b) => strnatcasecmp((string) $a->getTranslated()['name'], (string) $b->getTranslated()['name']));

        return $this;
    }

    public function getApiAlias(): string
    {
        return 'category_collection';
    }

    protected function getExpectedClass(): string
    {
        return CategoryEntity::class;
    }
}
