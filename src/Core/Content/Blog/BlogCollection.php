<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @template TElement of BlogEntity = BlogEntity
 *
 * @extends EntityCollection<TElement>
 */
class BlogCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_collection';
    }

    /**
     * @return class-string<BlogEntity>
     */
    protected function getExpectedClass(): string
    {
        return BlogEntity::class;
    }
}
