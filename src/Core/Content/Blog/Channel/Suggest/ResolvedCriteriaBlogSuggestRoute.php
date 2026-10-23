<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Suggest;

use Contena\Core\Content\Blog\Aggregate\BlogVisibility\BlogVisibilityDefinition;
use Contena\Core\Content\Blog\Events\BlogSuggestCriteriaEvent;
use Contena\Core\Content\Blog\Events\BlogSuggestResultEvent;
use Contena\Core\Content\Blog\BlogEvents;
use Contena\Core\Content\Blog\BlogException;
use Contena\Core\Content\Blog\Channel\Listing\Processor\CompositeListingProcessor;
use Contena\Core\Content\Blog\Channel\BlogAvailableFilter;
use Contena\Core\Content\Blog\SearchKeyword\BlogSearchBuilderInterface;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ResolvedCriteriaBlogSuggestRoute extends AbstractBlogSuggestRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BlogSearchBuilderInterface $searchBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractBlogSuggestRoute $decorated,
        private readonly CompositeListingProcessor $processor
    ) {
    }

    public function getDecorated(): AbstractBlogSuggestRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, ChannelContext $context, Criteria $criteria): BlogSuggestRouteResponse
    {
        if (!RequestParamHelper::get($request, 'search')) {
            throw BlogException::missingRequestParameter('search');
        }

        $criteria->addState(BlogSuggestRoute::STATE);
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);

        $criteria->addFilter(
            new BlogAvailableFilter($context->getChannelId(), BlogVisibilityDefinition::VISIBILITY_SEARCH)
        );

        $this->searchBuilder->build($request, $criteria, $context);

        $this->processor->prepare($request, $criteria, $context);

        $this->eventDispatcher->dispatch(
            new BlogSuggestCriteriaEvent($request, $criteria, $context),
            BlogEvents::BLOG_SUGGEST_CRITERIA
        );

        $response = $this->getDecorated()->load($request, $context, $criteria);

        $this->processor->process($request, $response->getListingResult(), $context);

        $this->eventDispatcher->dispatch(
            new BlogSuggestResultEvent($request, $response->getListingResult(), $context),
            BlogEvents::BLOG_SUGGEST_RESULT
        );

        return $response;
    }
}
