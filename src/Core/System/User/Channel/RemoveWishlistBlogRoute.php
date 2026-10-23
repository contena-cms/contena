<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Event\WishlistBlogRemovedEvent;
use Contena\Core\System\User\Extension\RemoveWishlistBlogRouteExtension;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class RemoveWishlistBlogRoute extends AbstractRemoveWishlistBlogRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<UserWishlistCollection> $wishlistRepository
     * @param EntityRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly EntityRepository $wishlistRepository,
        private readonly EntityRepository $blogRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractRemoveWishlistBlogRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/user/wishlist/delete/{blogId}',
        name: 'store-api.user.wishlist.delete',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_DELETE]
    )]
    public function delete(string $blogId, ChannelContext $context, UserEntity $user): SuccessResponse
    {
        return $this->extensions->publish(
            name: RemoveWishlistBlogRouteExtension::NAME,
            extension: new RemoveWishlistBlogRouteExtension($blogId, $context, $user),
            function: $this->_delete(...),
        );
    }

    private function _delete(string $blogId, ChannelContext $context, UserEntity $user): SuccessResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getChannelId())) {
            throw UserException::userWishlistNotActivated();
        }

        $wishlistId = $this->getWishlistId($context, $user->getId());

        $wishlistBlogId = $this->getWishlistBlogId($wishlistId, $blogId, $context);

        $this->blogRepository->delete([
            [
                'id' => $wishlistBlogId,
            ],
        ], $context->getContext());

        $this->eventDispatcher->dispatch(new WishlistBlogRemovedEvent($wishlistId, $blogId, $context));

        return new SuccessResponse();
    }

    private function getWishlistId(ChannelContext $context, string $userId): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('userId', $userId),
                new EqualsFilter('channelId', $context->getChannelId()),
            ]));

        $wishlistId = $this->wishlistRepository->searchIds($criteria, $context->getContext())->firstId();
        if (!$wishlistId) {
            throw UserException::userWishlistNotFound();
        }

        return $wishlistId;
    }

    private function getWishlistBlogId(string $wishlistId, string $blogId, ChannelContext $context): string
    {
        $criteria = (new Criteria())
            ->setLimit(1)
            ->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
                new EqualsFilter('wishlistId', $wishlistId),
                new EqualsFilter('blogId', $blogId),
                new EqualsFilter('blogVersionId', Defaults::LIVE_VERSION),
            ]));

        $wishlistBlogId = $this->blogRepository->searchIds($criteria, $context->getContext())->firstId();
        if (!$wishlistBlogId) {
            throw UserException::wishlistBlogNotFound($blogId);
        }

        return $wishlistBlogId;
    }
}
