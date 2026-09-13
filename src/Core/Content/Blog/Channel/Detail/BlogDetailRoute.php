<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Detail;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Contena\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Contena\Core\Content\Category\CategoryEntity;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class BlogDetailRoute extends AbstractBlogDetailRoute
{
    /**
     * Opt out of loading the breadcrumb. Clients pass it as the `skipBreadcrumb` query or body parameter; internal
     * callers set it as a request attribute, which takes precedence and cannot be provided by a client.
     */
    final public const SKIP_BREADCRUMB = 'skipBreadcrumb';

    /**
     * Build the breadcrumb along the category the blog was linked from, instead of its SEO category. Clients pass it
     * as the `referrerCategoryId` query or body parameter; an internal request attribute takes precedence.
     */
    final public const REFERRER_CATEGORY_ID = 'referrerCategoryId';

    /**
     * @internal
     *
     * @param ChannelRepository<ChannelBlogCollection> $blogRepository
     */
    public function __construct(
        private readonly ChannelRepository $blogRepository,
        private readonly CategoryBreadcrumbBuilder $breadcrumbBuilder,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public static function buildName(string $blogId): string
    {
        return EntityCacheKeyGenerator::buildBlogTag($blogId);
    }

    public function getDecorated(): AbstractBlogDetailRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/blog/{blogId}',
        name: 'channel-api.blog.detail',
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => BlogDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_POST, Request::METHOD_GET]
    )]
    public function load(string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogDetailRouteResponse
    {
        $criteria->setIds([$blogId]);
        $criteria->setTitle('blog-detail-route');
        $criteria->addFilter(new BlogAvailableFilter($context->getChannelId(), BlogVisibilityDefinition::VISIBILITY_LINK));

        $blog = $this->blogRepository->search($criteria, $context)->getEntities()->first();
        if (!$blog instanceof ChannelBlogEntity) {
            throw BlogException::blogNotFound($blogId);
        }

        $this->cacheTagCollector->addTag(self::buildName($blogId));
        $seoCategory = $this->getBreadcrumbCategory($request, $blog, $context);
        $blog->setSeoCategory($seoCategory);

        if ($seoCategory !== null && !$this->skipBreadcrumb($request)) {
            $blog->setSeoBreadcrumb($this->loadBreadcrumb($seoCategory, $context));
        }

        return new BlogDetailRouteResponse($blog);
    }

    private function getBreadcrumbCategory(Request $request, ChannelBlogEntity $blog, ChannelContext $context): ?CategoryEntity
    {
        $referrerCategoryId = $this->getReferrerCategoryId($request);

        if ($referrerCategoryId !== null) {
            return $this->breadcrumbBuilder->getBlogCategoryByReferrer($referrerCategoryId, $blog, $context);
        }

        return $this->breadcrumbBuilder->getBlogSeoCategory($blog, $context);
    }

    private function getReferrerCategoryId(Request $request): ?string
    {
        $referrerCategoryId = $request->attributes->has(self::REFERRER_CATEGORY_ID)
            ? $request->attributes->get(self::REFERRER_CATEGORY_ID)
            : RequestParamHelper::get($request, self::REFERRER_CATEGORY_ID);

        if (!\is_string($referrerCategoryId) || $referrerCategoryId === '') {
            return null;
        }

        return $referrerCategoryId;
    }

    private function loadBreadcrumb(CategoryEntity $seoCategory, ChannelContext $context): BreadcrumbCollection
    {
        $breadcrumb = $this->breadcrumbBuilder->getCategoryBreadcrumbUrls(
            $seoCategory,
            $context->getContext(),
            $context->getChannel()
        );

        // The breadcrumb reflects every category on the path, so all of them invalidate the cached response.
        $tags = $breadcrumb->map(static fn (Breadcrumb $item) => CategoryRoute::buildName($item->categoryId));

        if ($tags !== []) {
            $this->cacheTagCollector->addTag(...$tags);
        }

        return $breadcrumb;
    }

    private function skipBreadcrumb(Request $request): bool
    {
        if ($request->attributes->has(self::SKIP_BREADCRUMB)) {
            return $request->attributes->getBoolean(self::SKIP_BREADCRUMB);
        }

        return filter_var(RequestParamHelper::get($request, self::SKIP_BREADCRUMB, false), \FILTER_VALIDATE_BOOL);
    }
}
