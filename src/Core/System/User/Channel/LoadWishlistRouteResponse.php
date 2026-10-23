<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Struct\ArrayStruct;
use Contena\Core\System\Channel\StoreApiResponse;
use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistEntity;

/**
 * @extends StoreApiResponse<ArrayStruct<array{wishlist: UserWishlistEntity, blogs: EntitySearchResult<BlogCollection>}>>
 */
class LoadWishlistRouteResponse extends StoreApiResponse
{
    /**
     * @param EntitySearchResult<BlogCollection> $blogListing
     */
    public function __construct(
        protected UserWishlistEntity $wishlist,
        protected EntitySearchResult $blogListing,
    ) {
        parent::__construct(new ArrayStruct([
            'wishlist' => $wishlist,
            'blogs' => $blogListing,
        ], 'wishlist_products'));
    }

    public function getWishlist(): UserWishlistEntity
    {
        return $this->wishlist;
    }

    public function setWishlist(UserWishlistEntity $wishlist): void
    {
        $this->wishlist = $wishlist;
    }

    /**
     * @return EntitySearchResult<BlogCollection>
     */
    public function getBlogListing(): EntitySearchResult
    {
        return $this->blogListing;
    }

    /**
     * @param EntitySearchResult<BlogCollection> $blogListing
     */
    public function setBlogListing(EntitySearchResult $blogListing): void
    {
        $this->blogListing = $blogListing;
    }
}
