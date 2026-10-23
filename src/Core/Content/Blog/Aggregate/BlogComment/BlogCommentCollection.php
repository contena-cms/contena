<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Aggregate\BlogComment;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<BlogCommentEntity>
 *
 * @codeCoverageIgnore
 */
class BlogCommentCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'blog_comment_collection';
    }

    protected function getExpectedClass(): string
    {
        return BlogCommentEntity::class;
    }
}
