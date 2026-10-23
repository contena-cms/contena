<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Wishlist;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
class GuestWishlistPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected GuestWishlistPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): GuestWishlistPage
    {
        return $this->page;
    }
}
