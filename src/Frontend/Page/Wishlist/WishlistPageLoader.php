<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Wishlist;

use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistEntity;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\Channel\AbstractLoadWishlistRoute;
use Contena\Core\System\User\Channel\LoadWishlistRouteResponse;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class WishlistPageLoader
{
    private const DEFAULT_LIMIT = 24;

    private const DEFAULT_PAGE = 1;

    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly AbstractLoadWishlistRoute $wishlistLoadRoute,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws UserNotLoggedInException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, ChannelContext $context, UserEntity $user): WishlistPage
    {
        $criteria = $this->createCriteria($request);
        $this->eventDispatcher->dispatch(new WishListPageBlogCriteriaEvent($criteria, $context, $request));

        $page = $this->genericLoader->load($request, $context);
        $page->getMetaInformation()?->setRobots('noindex,follow');

        $page = WishlistPage::createFrom($page);

        try {
            $page->setWishlist($this->wishlistLoadRoute->load($request, $context, $criteria, $user));
        } catch (UserWishlistNotFoundException) {
            $page->setWishlist(
                new LoadWishlistRouteResponse(
                    new UserWishlistEntity(),
                    new EntitySearchResult(
                        'wishlist',
                        0,
                        new BlogCollection(),
                        null,
                        $criteria,
                        $context->getContext()
                    )
                )
            );
        }

        $this->eventDispatcher->dispatch(
            new WishlistPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function createCriteria(Request $request): Criteria
    {
        $limit = self::DEFAULT_LIMIT;
        $page = $request->query->get('p');
        $page = $page ? (int) $page : self::DEFAULT_PAGE;
        $offset = $limit * ($page - 1);

        return (new Criteria())
            ->setTitle('wishlist::page')
            ->addSorting(new FieldSorting('wishlists.updatedAt', FieldSorting::ASCENDING))
            ->addAssociation('manufacturer')
            ->addAssociation('options.group')
            ->setLimit($limit)
            ->setOffset($offset)
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
    }
}
