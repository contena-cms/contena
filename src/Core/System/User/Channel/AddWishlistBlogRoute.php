<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistCollection;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Contena\Core\System\User\Event\WishlistBlogAddedEvent;
use Contena\Core\System\User\Extension\AddWishlistBlogRouteExtension;
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
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class AddWishlistBlogRoute extends AbstractAddWishlistBlogRoute
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
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractAddWishlistBlogRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/user/wishlist/add/{blogId}',
        name: 'store-api.user.wishlist.add',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function add(string $blogId, ChannelContext $context, UserEntity $user): SuccessResponse
    {
        return $this->extensions->publish(
            name: AddWishlistBlogRouteExtension::NAME,
            extension: new AddWishlistBlogRouteExtension($blogId, $context, $user),
            function: $this->_add(...),
        );
    }

    private function _add(string $blogId, ChannelContext $context, UserEntity $user): SuccessResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getChannelId())) {
            throw UserException::userWishlistNotActivated();
        }

        $this->validateBlog($blogId, $context);
        $wishlistId = $this->getWishlistId($context, $user->getId());

        $this->wishlistRepository->upsert([
            [
                'id' => $wishlistId,
                'userId' => $user->getId(),
                'channelId' => $context->getChannelId(),
                'blogs' => [
                    [
                        'blogId' => $blogId,
                        'blogVersionId' => Defaults::LIVE_VERSION,
                    ],
                ],
            ],
        ], $context->getContext());

        $this->eventDispatcher->dispatch(new WishlistBlogAddedEvent($wishlistId, $blogId, $context));

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

        return $this->wishlistRepository->searchIds($criteria, $context->getContext())->firstId() ?? Uuid::randomHex();
    }

    private function validateBlog(string $blogId, ChannelContext $context): void
    {
        $total = $this->blogRepository->searchIds(new Criteria([$blogId]), $context)->getTotal();
        if ($total === 0) {
            throw UserException::blogNotFound($blogId);
        }
    }
}
