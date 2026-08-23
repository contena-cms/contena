<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogMainCategory;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogMainCategoryEntity>
 */
class BlogMainCategoryCollection extends EntityCollection
{
    public function filterByChannelId(string $id): BlogMainCategoryCollection
    {
        return $this->filter(static fn (BlogMainCategoryEntity $mainCategory) => $mainCategory->getChannelId() === $id);
    }

    public function getApiAlias(): string
    {
        return 'blog_main_category_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogMainCategoryEntity::class;
    }
}
