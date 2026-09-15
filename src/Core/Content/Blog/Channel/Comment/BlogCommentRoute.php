<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment;

use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentCollection;
use Contena\Core\Content\Blog\Aggregate\BlogComment\BlogCommentDefinition;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Detail\BlogDetailRoute;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\EntityRepository;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class BlogCommentRoute extends AbstractBlogCommentRoute
{
    /**
     * @internal
     *
     * @param EntityRepository<BlogCommentCollection> $blogCommentRepository
     */
    public function __construct(
        private readonly EntityRepository $blogCommentRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly CacheTagCollector $cacheTagCollector,
    ) {
    }

    public function getDecorated(): AbstractBlogCommentRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/blog/{blogId}/comments',
        name: 'channel-api.blog-comment.list',
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => BlogCommentDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
        methods: [Request::METHOD_POST, Request::METHOD_GET],
    )]
    public function load(string $blogId, Request $request, ChannelContext $context, Criteria $criteria): BlogCommentRouteResponse
    {
        $channelId = $context->getChannelId();
        if (!$this->systemConfigService->getBool('core.listing.showComments', $channelId)) {
            throw BlogException::commentsNotActive();
        }

        $this->cacheTagCollector->addTag(BlogDetailRoute::buildName($blogId));

        $visible = $this->visibleFilter($context);
        $criteria->setTitle('blog-comment-route');
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_AND, [
            $visible,
            new EqualsFilter('blogId', $blogId),
            new EqualsFilter('channelId', $channelId),
            new EqualsFilter('parentId', null),
        ]));
        $criteria->addAssociation('language.translationCode');

        $children = $criteria->getAssociation('children');
        $children->addFilter($this->visibleFilter($context));
        $children->addSorting(new FieldSorting('createdAt', FieldSorting::ASCENDING));
        $children->addAssociation('language.translationCode');

        $result = $this->blogCommentRepository->search($criteria, $context->getContext());

        return new BlogCommentRouteResponse($result);
    }

    private function visibleFilter(ChannelContext $context): MultiFilter
    {
        $visible = new MultiFilter(MultiFilter::CONNECTION_OR, [new EqualsFilter('status', true)]);
        if ($member = $context->getMember()) {
            $visible->addQuery(new EqualsFilter('memberId', $member->getId()));
        }

        return $visible;
    }
}
