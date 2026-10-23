<?php declare(strict_types=1);

namespace Contena\Core\Content\Cms\Channel\Struct;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Framework\Struct\Struct;

/**
 * @codeCoverageIgnore
 */
class BlogDescriptionReviewsStruct extends Struct
{
    protected ?string $blogId = null;

    protected ?bool $ratingSuccess = null;


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



    public function getRatingSuccess(): ?bool
    {
        return $this->ratingSuccess;
    }

    public function setRatingSuccess(bool $rateSuccess): void
    {
        $this->ratingSuccess = $rateSuccess;
    }

    public function getApiAlias(): string
    {
        return 'cms_product_description_reviews';
    }
}
