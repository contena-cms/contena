<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistCollection;
use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistEntity;
use Contena\Core\System\User\Event\UserWishlistBlogListingResultEvent;
use Contena\Core\System\User\Event\UserWishlistLoaderCriteriaEvent;
use Contena\Core\System\User\Extension\LoadWishlistRouteExtension;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class LoadWishlistRoute extends AbstractLoadWishlistRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<UserWishlistCollection> $wishlistRepository
     * @param ChannelRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly ChannelRepository $blogRepository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractBlogCloseoutFilterFactory $blogCloseoutFilterFactory,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractLoadWishlistRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/user/wishlist',
        name: 'store-api.user.wishlist.load',
        defaults: [
            PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true,
            PlatformRequest::ATTRIBUTE_ENTITY => BlogDefinition::ENTITY_NAME,
        ],
        methods: [Request::METHOD_GET, Request::METHOD_POST],
    )]
    public function load(Request $request, ChannelContext $context, Criteria $criteria, UserEntity $user): LoadWishlistRouteResponse
    {
        return $this->extensions->publish(
            name: LoadWishlistRouteExtension::NAME,
            extension: new LoadWishlistRouteExtension($request, $context, $criteria, $user),
            function: $this->_load(...),
        );
    }

    private function _load(Request $request, ChannelContext $context, Criteria $criteria, UserEntity $user): LoadWishlistRouteResponse
    {
        if (!$criteria->getTitle()) {
            $criteria->setTitle('wishlist::load-blogs');
        }

        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getChannelId())) {
            throw UserException::userWishlistNotActivated();
        }

        $wishlist = $this->loadWishlist($context, $user->getId());
        $blogs = $this->loadBlogs($wishlist->getId(), $criteria, $context, $request);

        return new LoadWishlistRouteResponse($wishlist, $blogs);
    }

    private function loadWishlist(ChannelContext $context, string $userId): UserWishlistEntity
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('userId', $userId),
                new EqualsFilter('channelId', $context->getChannelId()),
            ]));

        $result = $this->wishlistRepository->search($criteria, $context->getContext())->getEntities()->first();
        if (!$result) {
            throw UserException::userWishlistNotFound();
        }

        return $result;
    }

    /**
     * @return EntitySearchResult<BlogCollection>
     */
    private function loadBlogs(string $wishlistId, Criteria $criteria, ChannelContext $context, Request $request): EntitySearchResult
    {
        $criteria
            ->addFilter(new EqualsFilter('wishlists.wishlistId', $wishlistId))
            ->addSorting(new FieldSorting('wishlists.updatedAt', FieldSorting::DESCENDING))
            ->addSorting(new FieldSorting('wishlists.createdAt', FieldSorting::DESCENDING));

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutBlogsWhenOutOfStock',
            $context->getChannelId()
        )) {
            $criteria->addFilter($this->blogCloseoutFilterFactory->create($context));
        }

        $event = new UserWishlistLoaderCriteriaEvent($criteria, $context);
        $this->eventDispatcher->dispatch($event);

        $blogs = $this->blogRepository->search($criteria, $context);

        $event = new UserWishlistBlogListingResultEvent($request, $blogs, $context);
        $this->eventDispatcher->dispatch($event);

        return $blogs;
    }
}
