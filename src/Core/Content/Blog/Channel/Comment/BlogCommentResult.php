<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Struct\Struct;

class BlogCommentResult extends Struct
{
    /**
     * @param EntitySearchResult<BlogCommentCollection> $comments
     */
    public function __construct(
        protected readonly EntitySearchResult $comments,
        protected readonly string $blogId,
    ) {
    }

    /**
     * @return EntitySearchResult<BlogCommentCollection>
     */
    public function getComments(): EntitySearchResult
    {
        return $this->comments;
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }
}
