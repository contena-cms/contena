<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogCrossSellingAssignedBlogs;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<BlogCrossSellingAssignedBlogsEntity>
 */
class BlogCrossSellingAssignedBlogsCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_cross_selling_assigned_products_collection';
    }

    /**
     * @return array<string>
     */
    public function getBlogIds(): array
    {
        return $this->fmap(static fn (BlogCrossSellingAssignedBlogsEntity $entity) => $entity->getBlogId());
    }

    public function sortByPosition(): void
    {
        $this->sort(static fn (BlogCrossSellingAssignedBlogsEntity $a, BlogCrossSellingAssignedBlogsEntity $b) => $a->getPosition() <=> $b->getPosition());
    }

    protected function getExpectedClass(): string
    {
        return BlogCrossSellingAssignedBlogsEntity::class;
    }
}
