<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Sorting;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogSortingEntity>
 */
class BlogSortingCollection extends EntityCollection
{
    /**
     * @param string[] $keys
     */
    public function sortByKeyArray(array $keys): void
    {
        $sorted = [];

        foreach ($keys as $key) {
            $sorting = $this->getByKey($key);
            if ($sorting !== null) {
                $sorted[$sorting->getId()] = $this->elements[$sorting->getId()];
            }
        }

        $this->elements = $sorted;
    }

    public function getByKey(string $key): ?BlogSortingEntity
    {
        return $this->filterByProperty('key', $key)->first();
    }

    public function removeByKey(string $key): void
    {
        foreach ($this->elements as $element) {
            if ($element->getKey() === $key) {
                $this->remove($element->getId());
            }
        }
    }

    public function getApiAlias(): string
    {
        return 'blog_sorting_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogSortingEntity::class;
    }
}
