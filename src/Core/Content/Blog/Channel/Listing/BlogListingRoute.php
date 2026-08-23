<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing;

use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Content\Blog\Events\BlogListingCriteriaEvent;
use Contena\Core\Content\Blog\Events\BlogListingResultEvent;
use Contena\Core\Content\Category\Channel\CategoryRoute;
use Contena\Core\Framework\Adapter\Cache\CacheTagCollector;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class BlogListingRoute extends AbstractBlogListingRoute
{
    public const string STATE = 'listing-route-context';

    /**
     * @internal
     *
     * @param ChannelRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly ChannelRepository $blogRepository,
        private readonly CacheTagCollector $cacheTagCollector,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CompositeListingProcessor $processor,
    ) {
    }

    public function getDecorated(): AbstractBlogListingRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(
        path: '/channel-api/blog-listing/{categoryId}',
        name: 'channel-api.blog.listing',
        methods: [Request::METHOD_GET, Request::METHOD_POST],
        defaults: [PlatformRequest::ATTRIBUTE_ENTITY => BlogDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true],
    )]
    public function load(string $categoryId, Request $request, ChannelContext $context, Criteria $criteria): BlogListingRouteResponse
    {
        $criteria->addState(self::STATE);
        $criteria->addFilter(new EqualsFilter('categoriesRo.id', $categoryId));
        $this->processor->prepare($request, $criteria, $context);
        $criteria->setTitle('blog-listing-route');
        $this->eventDispatcher->dispatch(new BlogListingCriteriaEvent($request, $criteria, $context));
        $this->processor->resolve($request, $criteria, $context);
        $this->cacheTagCollector->addTag(CategoryRoute::buildName($categoryId));

        $result = BlogListingResult::fromSearchResult($this->blogRepository->search($criteria, $context));
        $result->addCurrentFilter('navigationId', $categoryId);
        $this->processor->process($request, $result, $context);
        $this->eventDispatcher->dispatch(new BlogListingResultEvent($request, $result, $context));

        return new BlogListingRouteResponse($result);
    }
}
