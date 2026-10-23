<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @template TElement of BlogEntity = BlogEntity
 *
 * @extends EntityCollection<TElement>
 *
 * @codeCoverageIgnore
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
