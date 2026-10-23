<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Wishlist;

use Contena\Core\System\User\Channel\LoadWishlistRouteResponse;
use Contena\Frontend\Page\Page;

class WishlistPage extends Page
{
    protected LoadWishlistRouteResponse $wishlist;

    public function getWishlist(): LoadWishlistRouteResponse
    {
        return $this->wishlist;
    }

    public function setWishlist(LoadWishlistRouteResponse $wishlist): void
    {
        $this->wishlist = $wishlist;
    }
}
