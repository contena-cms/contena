<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Extension\BlogCommentRouteExtension;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\CompressedCriteriaDecoder;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\RequestCriteriaBuilder;
use Contena\Core\Framework\Extensions\ExtensionDispatcher;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class BlogCommentRoute extends AbstractBlogCommentRoute
{
    public const DEFAULT_MAX_LIMIT = 100;

    /**
     * @internal
     *
     * @param EntityRepository<BlogCommentCollection> $blogCommentRepository
     */
    public function __construct(
        private readonly EntityRepository $blogCommentRepository,
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

    public function getDecorated(): AbstractBlogCommentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/blog/{blogId}/comments',
        name: 'channel-api.blog-comment.list',
        methods: [Request::METHOD_POST, Request::METHOD_GET],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => BlogCommentDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true]
    )]
    public function load(string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogCommentRouteResponse
    {
        return $this->extensions->publish(
            name: BlogCommentRouteExtension::NAME,
            extension: new BlogCommentRouteExtension(
                $blogId,
                $request,
                $context,
                $this->applyConfiguredLimit($criteria, $context->getChannelId(), $request),
            ),
            function: fn (string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogCommentRouteResponse => $this->_load($blogId, $context, $criteria),
        );
    }

    private function _load(string $blogId, ChannelContext $context, Criteria $criteria): BlogCommentRouteResponse
    {
        $channelId = $context->getChannelId();
        if (!$this->systemConfigService->getBool('core.listing.showComment', $channelId)) {
            throw BlogException::commentNotActive();
        }

        $this->cacheTagCollector->addTag(self::buildName($blogId));

        $active = new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('status', true)]);
        if ($user = $context->getUser()) {
            $active->addQuery(new EqualsFilter('userId', $user->getId()));
        }

        $criteria->setTitle('blog-comment-route');
        $criteria->addFilter(
            new MultiFilter(MultiFilter::CONNECTION_AND, [
                $active,
            ])
        );

        $result = $this->blogCommentRepository->search($criteria, $context->getContext());

        return new BlogCommentRouteResponse($result);
    }

    private function applyConfiguredLimit(Criteria $criteria, string $channelId, Request $request): Criteria
    {
        if (!$criteria->hasState(RequestCriteriaBuilder::STATE_NO_EXPLICIT_LIMIT_IN_REQUEST)) {
            return $criteria;
        }

        $commentsPerPage = $this->systemConfigService->getInt('core.listing.commentsPerPage', $channelId);
        $commentsPerPage = min($commentsPerPage, $this->maxLimit);
        if ($commentsPerPage <= 0) {
            return $criteria;
        }

        // The offset was derived from the max limit while resolving the page, so
        // recompute it for the configured page size to keep pagination consistent.
        $currentLimit = $criteria->getLimit();
        $currentOffset = $criteria->getOffset();
        if ($currentLimit && $currentOffset) {
            $page = intdiv($currentOffset, $currentLimit) + 1;
            $criteria->setOffset($commentsPerPage * ($page - 1));
        }

        $criteria->setLimit($commentsPerPage);
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
