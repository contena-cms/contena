<?php declare(strict_types=1);

namespace Contena\Core\System\User\Aggregate\UserWishlistBlog;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<UserWishlistBlogEntity>
 *
 * @codeCoverageIgnore
 */
class UserWishlistBlogCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'user_wishlist_product_collection';
    }

    public function getBlogs(): ?BlogCollection
    {
        return new BlogCollection($this->fmap(static fn (UserWishlistBlogEntity $wishlistBlogEntity) => $wishlistBlogEntity->getBlog()));
    }

    public function getByBlogId(string $blogId): ?UserWishlistBlogEntity
    {
        return $this->filterByProperty('blogId', $blogId)->first();
    }

    protected function getExpectedClass(): string
    {
        return UserWishlistBlogEntity::class;
    }
}
