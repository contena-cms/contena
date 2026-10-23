<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Wishlist;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class GuestWishlistPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericPageLoader,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function load(Request $request, ChannelContext $context): GuestWishlistPage
    {
        $page = $this->genericPageLoader->load($request, $context);
        $page->getMetaInformation()?->setRobots('noindex,follow');

        $page = GuestWishlistPage::createFrom($page);

        $this->eventDispatcher->dispatch(new GuestWishlistPageLoadedEvent($page, $context, $request));

        return $page;
    }
}
