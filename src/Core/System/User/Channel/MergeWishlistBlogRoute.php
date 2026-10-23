<?php declare(strict_types=1);

namespace Contena\Core\System\User\Channel;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Defaults;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\Framework\Validation\DataBag\RequestDataBag;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Contena\Core\System\Channel\SuccessResponse;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Contena\Core\System\User\Aggregate\UserWishlist\UserWishlistCollection;
use Contena\Core\System\User\Event\WishlistMergedEvent;
use Contena\Core\System\User\Extension\MergeWishlistBlogRouteExtension;
use Contena\Core\System\User\UserEntity;
use Contena\Core\System\User\UserException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class MergeWishlistBlogRoute extends AbstractMergeWishlistBlogRoute
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
        private readonly Connection $connection,
        private readonly ExtensionDispatcher $extensions
    ) {
    }

    public function getDecorated(): AbstractMergeWishlistBlogRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/user/wishlist/merge',
        name: 'store-api.user.wishlist.merge',
        defaults: [PlatformRequest::ATTRIBUTE_LOGIN_REQUIRED => true],
        methods: [Request::METHOD_POST]
    )]
    public function merge(RequestDataBag $data, ChannelContext $context, UserEntity $user): SuccessResponse
    {
        return $this->extensions->publish(
            name: MergeWishlistBlogRouteExtension::NAME,
            extension: new MergeWishlistBlogRouteExtension($data, $context, $user),
            function: $this->_merge(...),
        );
    }

    private function _merge(RequestDataBag $data, ChannelContext $context, UserEntity $user): SuccessResponse
    {
        if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $context->getChannelId())) {
            throw UserException::userWishlistNotActivated();
        }

        $wishlistId = $this->getWishlistId($context, $user->getId());

        $upsertData = $this->buildUpsertBlogs($data, $wishlistId, $context);

        $this->wishlistRepository->upsert([[
            'id' => $wishlistId,
            'userId' => $user->getId(),
            'channelId' => $context->getChannelId(),
            'blogs' => $upsertData,
        ]], $context->getContext());

        $this->eventDispatcher->dispatch(new WishlistMergedEvent($upsertData, $context));

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

    /**
     * @return array<array{id: string, blogId?: string, blogVersionId?: Defaults::LIVE_VERSION}>
     */
    private function buildUpsertBlogs(RequestDataBag $data, string $wishlistId, ChannelContext $context): array
    {
        $blogIds = $data->get('blogIds');
        if (!$blogIds instanceof DataBag) {
            throw UserException::blogIdsParameterIsMissing();
        }

        $ids = array_unique(array_filter($blogIds->all()));

        if ($ids === []) {
            return [];
        }

        $ids = $this->blogRepository->searchIds(new Criteria($ids), $context)->getIds();

        $userBlogs = $this->loadUserBlogs($wishlistId, $ids);

        $upsertData = [];

        foreach ($ids as $id) {
            if (\array_key_exists($id, $userBlogs)) {
                $upsertData[] = [
                    'id' => $userBlogs[$id],
                ];

                continue;
            }

            $upsertData[] = [
                'id' => Uuid::randomHex(),
                'blogId' => $id,
                'blogVersionId' => Defaults::LIVE_VERSION,
            ];
        }

        return $upsertData;
    }

    /**
     * @param array<string> $blogIds
     *
     * @return array<string, string>
     */
    private function loadUserBlogs(string $wishlistId, array $blogIds): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(
            'LOWER(HEX(`blog_id`)) as `blog_id`',
            'LOWER(HEX(`id`)) as id',
        );
        $query->from('`user_wishlist_blog`');
        $query->where('`user_wishlist_id` = :id');
        $query->andWhere('`blog_id` IN (:blogIds)');
        $query->setParameter('id', Uuid::fromHexToBytes($wishlistId));
        $query->setParameter('blogIds', Uuid::fromHexToBytesList($blogIds), ArrayParameterType::BINARY);
        $result = $query->executeQuery();

        /** @var array<string, string> $values */
        $values = $result->fetchAllKeyValue();

        return $values;
    }
}
