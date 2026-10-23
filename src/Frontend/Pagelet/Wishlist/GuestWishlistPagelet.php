<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Wishlist;

use Contena\Core\Content\Blog\Channel\BlogListResponse;
use Contena\Core\Framework\Log\Package;
use Contena\Frontend\Pagelet\Pagelet;

class GuestWishlistPagelet extends Pagelet
{
    protected BlogListResponse $searchResult;

    public function getSearchResult(): BlogListResponse
    {
        return $this->searchResult;
    }

    public function setSearchResult(BlogListResponse $searchResult): void
    {
        $this->searchResult = $searchResult;
    }
}
