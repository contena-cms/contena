<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Review;

use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewCollection;
use Contena\Core\Content\Blog\Aggregate\BlogReview\BlogReviewDefinition;
use Contena\Core\Content\Blog\Extension\BlogReviewRouteExtension;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\StoreApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [StoreApiRouteScope::ID]])]
class BlogReviewRoute extends AbstractBlogReviewRoute
{
    public const DEFAULT_MAX_LIMIT = 100;

    /**
     * @internal
     *
     * @param EntityRepository<BlogReviewCollection> $blogReviewRepository
     */
    public function __construct(
        private readonly EntityRepository $blogReviewRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly ExtensionDispatcher $extensions,
        private readonly int $maxLimit = self::DEFAULT_MAX_LIMIT,
        private readonly CompressedCriteriaDecoder $compressedCriteriaDecoder = new CompressedCriteriaDecoder(),
    ) {
    }

    public static function buildName(string $parentId): string
    {
        return EntityCacheKeyGenerator::buildBlogTag($parentId);
    }

    public function getDecorated(): AbstractBlogReviewRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/store-api/blog/{blogId}/reviews',
        name: 'store-api.blog-review.list',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => BlogReviewDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogReviewRouteResponse
    {
        return $this->extensions->publish(
            name: BlogReviewRouteExtension::NAME,
            extension: new BlogReviewRouteExtension(
                $blogId,
                $request,
                $context,
                $this->applyConfiguredLimit($criteria, $context->getChannelId(), $request),
            ),
            function: fn (string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogReviewRouteResponse => $this->_load($blogId, $context, $criteria),
        );
    }

    private function _load(string $blogId, ChannelContext $context, Criteria $criteria): BlogReviewRouteResponse
    {
        $channelId = $context->getChannelId();
        if (!$this->systemConfigService->getBool('core.listing.showReview', $channelId)) {
            throw BlogException::reviewNotActive();
        }

        $this->cacheTagCollector->addTag(self::buildName($blogId));

        $active = new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('status', true)]);
        if ($user = $context->getUser()) {
            $active->addQuery(new EqualsFilter('userId', $user->getId()));
        }

        $criteria->setTitle('blog-review-route');
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                $active,
                new MultiFilter(MultiFilter::CONNECTION_OR, [
                    new EqualsFilter('blog.id', $blogId),
                    new EqualsFilter('blog.parentId', $blogId),
                ]),
            ])
        );

        $result = $this->blogReviewRepository->search($criteria, $context->getContext());

        return new BlogReviewRouteResponse($result);
    }

    private function applyConfiguredLimit(Criteria $criteria, string $channelId, Request $request): Criteria
    {
        if (!$criteria->hasState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST)) {
            return $criteria;
        }

        $reviewsPerPage = $this->systemConfigService->getInt('core.listing.reviewsPerPage', $channelId);
        $reviewsPerPage = min($reviewsPerPage, $this->maxLimit);
        if ($reviewsPerPage <= 0) {
            return $criteria;
        }

        // The offset was derived from the max limit while resolving the page, so
        // recompute it for the configured page size to keep pagination consistent.
        $currentLimit = $criteria->getLimit();
        $currentOffset = $criteria->getOffset();
        if ($currentLimit && $currentOffset) {
            $page = intdiv($currentOffset, $currentLimit) + 1;
            $criteria->setOffset($reviewsPerPage * ($page - 1));
        }

        $criteria->setLimit($reviewsPerPage);
        if (!$this->hasExplicitTotalCountMode($request)) {
            $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        }

        $criteria->removeState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST);

        return $criteria;
    }

    private function hasExplicitTotalCountMode(Request $request): bool
    {
        if ($request->isMethod(Request::METHOD_GET)) {
            $payload = $request->query->has('_criteria')
                ? $this->compressedCriteriaDecoder->decode((string) $request->query->get('_criteria'))
                : $request->query->all();
        } else {
            $payload = $request->request->all();
        }

        return isset($payload['total-count-mode']);
    }
}
