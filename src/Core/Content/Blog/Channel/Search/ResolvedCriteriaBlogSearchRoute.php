<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Search;

use Contena\Core\Content\Blog\BlogEvents;
use Contena\Core\Content\Blog\Events\BlogSearchCriteriaEvent;
use Contena\Core\Content\Blog\Events\BlogSearchResultEvent;
use Contena\Core\Framework\Adapter\Request\RequestParamHelper;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ResolvedCriteriaBlogSearchRoute extends AbstractBlogSearchRoute
{
    final public const DEFAULT_SEARCH_SORT = 'score';
    final public const STATE = 'search-route-context';


    public function getDecorated(): AbstractBlogSearchRoute
    {
        return $this->decorated;
    }

    public function load(Request $request, ChannelContext $context, Criteria $criteria): BlogSearchRouteResponse
    {
        $criteria->addState(self::STATE);

        // will be handled via processor in next line
        $criteria->setLimit(null);

        $this->processor->prepare($request, $criteria, $context);

        $this->eventDispatcher->dispatch(
            new BlogSearchCriteriaEvent($request, $criteria, $context),
            BlogEvents::BLOG_SEARCH_CRITERIA
        );

        $response = $this->getDecorated()->load($request, $context, $criteria);

        $this->processor->process($request, $response->getListingResult(), $context);

        $this->eventDispatcher->dispatch(
            new BlogSearchResultEvent($request, $response->getListingResult(), $context),
            BlogEvents::BLOG_SEARCH_RESULT
        );

        $response->getListingResult()->addCurrentFilter('search', RequestParamHelper::get($request, 'search'));

        return $response;
    }
}
