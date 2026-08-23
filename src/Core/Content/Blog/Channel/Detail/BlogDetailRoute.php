<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Detail;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Blog\Channel\ChannelBlogCollection;
use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
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
        $blog->setSeoCategory($this->breadcrumbBuilder->getBlogCategoryByReferrer($request->query->get('referrerCategoryId', ''), $blog, $context));

        return new BlogDetailRouteResponse($blog);
    }
}
