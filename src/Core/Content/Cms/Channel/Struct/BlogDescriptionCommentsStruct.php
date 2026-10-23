<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Channel\Struct;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Blog\Channel\Comment\BlogCommentResult;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class BlogDescriptionCommentsStruct extends Struct
{
    protected ?string $blogId = null;

    protected ?bool $commentSuccess = null;

    protected ?BlogCommentResult $comments = null;

    protected ?ChannelBlogEntity $blog = null;

    public function getBlog(): ?ChannelBlogEntity
    {
        return $this->blog;
    }

    public function setBlog(ChannelBlogEntity $blog): void
    {
        $this->blog = $blog;
    }

    public function getBlogId(): ?string
    {
        return $this->blogId;
    }

    public function setBlogId(string $blogId): void
    {
        $this->blogId = $blogId;
    }

    public function getComments(): ?BlogCommentResult
    {
        return $this->comments;
    }

    public function setComments(BlogCommentResult $comments): void
    {
        $this->comments = $comments;
    }

    public function getCommentSuccess(): ?bool
    {
        return $this->commentSuccess;
    }

    public function setCommentSuccess(bool $commentSuccess): void
    {
        $this->commentSuccess = $commentSuccess;
    }

    public function getApiAlias(): string
    {
        return 'cms_blog_description_comments';
    }
}
