<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserWishlistBlog;

use Contena\Core\Content\Blog\BlogEntity;
use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistEntity;
use Contena\Core\Framework\DataAbstractionLayer\EntityIdTrait;

/**
 * @codeCoverageIgnore
 */
class UserWishlistBlogEntity extends Entity
{
    use EntityIdTrait;

    protected string $wishlistId;

    protected string $blogId;

    protected string $blogVersionId;

    protected ?UserWishlistEntity $wishlist = null;

    protected ?BlogEntity $blog = null;

    public function getWishlist(): ?UserWishlistEntity
    {
        return $this->wishlist;
    }

    public function setWishlist(UserWishlistEntity $wishlist): void
    {
        $this->wishlist = $wishlist;
    }

    public function getBlog(): ?BlogEntity
    {
        return $this->blog;
    }

    public function setBlog(BlogEntity $blog): void
    {
        $this->blog = $blog;
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function setWishlistId(string $wishlistId): void
    {
        $this->wishlistId = $wishlistId;
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function setBlogId(string $blogId): void
    {
        $this->blogId = $blogId;
    }

    public function getBlogVersionId(): string
    {
        return $this->blogVersionId;
    }

    public function setBlogVersionId(string $blogVersionId): void
    {
        $this->blogVersionId = $blogVersionId;
    }
}
