<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Listing;

use Contena\Core\Content\Blog\Channel\Search\ResolvedCriteriaBlogSearchRoute;
use Contena\Core\Content\Blog\Events\BlogListingCriteriaEvent;
use Contena\Core\Content\Blog\Events\BlogListingResultEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ResolveCriteriaBlogListingRoute extends AbstractBlogListingRoute
{
    public const STATE = 'listing-route-context';


    public function getDecorated(): AbstractBlogListingRoute
    {
        return $this->decorated;
    }

    public function load(string $categoryId, Request $request, ChannelContext $context, Criteria $criteria): BlogListingRouteResponse
    {
        $criteria->addState(self::STATE);

        $this->processor->prepare($request, $criteria, $context);

        $this->eventDispatcher->dispatch(
            new BlogListingCriteriaEvent($request, $criteria, $context)
        );

        $response = $this->getDecorated()->load($categoryId, $request, $context, $criteria);

        $response->getResult()->addCurrentFilter('navigationId', $categoryId);

        $this->processor->process($request, $response->getResult(), $context);

        $this->eventDispatcher->dispatch(
            new BlogListingResultEvent($request, $response->getResult(), $context)
        );

        $response->getResult()->getAvailableSortings()->removeByKey(
            ResolvedCriteriaBlogSearchRoute::DEFAULT_SEARCH_SORT
        );

        return $response;
    }
}
