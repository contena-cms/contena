<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Search;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\BlogCollection;
use Contena\Core\Content\Blog\BlogDefinition;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Content\Blog\Channel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Content\Blog\Events\BlogSearchCriteriaEvent;
use Contena\Core\Content\Blog\Events\BlogSearchResultEvent;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilderInterface;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Plugin\Exception\DecorationPatternException;
use Contena\Core\Framework\Routing\ChannelApiRouteScope;
use Contena\Core\PlatformRequest;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Core\System\Channel\Entity\ChannelRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: [PlatformRequest::ATTRIBUTE_ROUTE_SCOPE => [ChannelApiRouteScope::ID]])]
class BlogSearchRoute extends AbstractBlogSearchRoute
{
    public const string STATE = 'search-route-context';

    /**
     * @internal
     *
     * @param ChannelRepository<BlogCollection> $blogRepository
     */
    public function __construct(
        private readonly ChannelRepository $blogRepository,
        private readonly BlogSearchBuilderInterface $searchBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly CompositeListingProcessor $processor,
    ) {
    }

    public function getDecorated(): AbstractBlogSearchRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/channel-api/search', name: 'channel-api.search', methods: [Request::METHOD_POST, Request::METHOD_GET], defaults: [PlatformRequest::ATTRIBUTE_ENTITY => BlogDefinition::ENTITY_NAME, PlatformRequest::ATTRIBUTE_HTTP_CACHE => true])]
    public function load(Request $request, ChannelContext $context, Criteria $criteria): BlogSearchRouteResponse
    {
        $criteria->addState(self::STATE);
        $criteria->addFilter(new BlogAvailableFilter($context->getChannelId(), BlogVisibilityDefinition::VISIBILITY_SEARCH));
        $this->searchBuilder->build($request, $criteria, $context);
        $this->processor->prepare($request, $criteria, $context);
        $criteria->setTitle('blog-search-route');
        $this->eventDispatcher->dispatch(new BlogSearchCriteriaEvent($request, $criteria, $context));
        $this->processor->resolve($request, $criteria, $context);

        $result = BlogListingResult::fromSearchResult($this->blogRepository->search($criteria, $context));
        $this->processor->process($request, $result, $context);
        $this->eventDispatcher->dispatch(new BlogSearchResultEvent($request, $result, $context));

        return new BlogSearchRouteResponse($result);
    }
}
