<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Wishlist;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\Channel\AbstractBlogCloseoutFilterFactory;
use Contena\Core\Content\Blog\Channel\AbstractBlogListRoute;
use Contena\Core\Content\Blog\Channel\BlogListResponse;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\Framework\Uuid\Uuid;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageletLoader. Always use a channel-api route to get or put data.
 */
class GuestWishlistPageletLoader
{
    private const LIMIT = 100;

    /**
     * @internal
     */
    public function __construct(
        private readonly AbstractBlogListRoute $blogListRoute,
        private readonly SystemConfigService $systemConfigService,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractBlogCloseoutFilterFactory $blogCloseoutFilterFactory
    ) {
    }

    public function load(Request $request, ChannelContext $context): GuestWishlistPagelet
    {
        $page = new GuestWishlistPagelet();

        $blogsIds = $this->extractBlogIds($request);
        $criteria = $this->createCriteria($blogsIds, $context);
        $this->eventDispatcher->dispatch(new GuestWishListPageletBlogCriteriaEvent($criteria, $context, $request));

        if ($blogsIds !== []) {
            $response = $this->blogListRoute->load($criteria, $context);
        } else {
            $response = new BlogListResponse(new EntitySearchResult(
                'wishlist',
                0,
                new BlogCollection(),
                null,
                $criteria,
                $context->getContext()
            ));
        }

        $page->setSearchResult($response);

        $this->eventDispatcher->dispatch(new GuestWishlistPageletLoadedEvent($page, $context, $request));

        return $page;
    }

    /**
     * @return array<string>
     */
    private function extractBlogIds(Request $request): array
    {
        $blogIds = $request->request->all()['blogIds'] ?? [];

        if (!\is_array($blogIds)) {
            throw RoutingException::missingRequestParameter('blogIds');
        }

        /** @var array<string> $blogIds */
        return array_filter($blogIds, static fn (string $blogId) => Uuid::isValid($blogId));
    }

    /**
     * @param array<string> $blogIds
     */
    private function createCriteria(array $blogIds, ChannelContext $context): Criteria
    {
        $criteria = new Criteria();

        $criteria->setLimit(self::LIMIT);

        if ($blogIds !== []) {
            $criteria->setIds($blogIds);
        }

        $criteria->addAssociation('manufacturer')
            ->addAssociation('options.group')
            ->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        if ($this->systemConfigService->getBool(
            'core.listing.hideCloseoutBlogsWhenOutOfStock',
            $context->getChannelId()
        )) {
            $closeoutFilter = $this->blogCloseoutFilterFactory->create($context);
            $criteria->addFilter($closeoutFilter);
        }

        return $criteria;
    }
}
