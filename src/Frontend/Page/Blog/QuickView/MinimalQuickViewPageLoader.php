<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Blog\QuickView;

use Contena\Core\Content\Blog\Channel\Detail\AbstractBlogDetailRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Contena\Core\Framework\Log\Package;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
class MinimalQuickViewPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractBlogDetailRoute $blogRoute
    ) {
    }

    /**
     * @throws RoutingException
     */
    public function load(Request $request, ChannelContext $channelContext): MinimalQuickViewPage
    {
        $blogId = $request->attributes->get('blogId');
        if (!$blogId) {
            throw RoutingException::missingRequestParameter('blogId', '/blogId');
        }

        $criteria = (new Criteria())
            ->addAssociation('manufacturer.media')
            ->addAssociation('options.group')
            ->addAssociation('properties.group')
            ->addAssociation('mainCategories.category');

        $criteria
            ->getAssociation('media')
            ->addSorting(new FieldSorting('position'));

        $this->eventDispatcher->dispatch(new MinimalQuickViewPageCriteriaEvent($blogId, $criteria, $channelContext));

        $result = $this->blogRoute->load($blogId, $request->duplicate(), $channelContext, $criteria);
        $blog = $result->getBlog();

        $page = new MinimalQuickViewPage($blog);

        $event = new MinimalQuickViewPageLoadedEvent($page, $channelContext, $request);

        $this->eventDispatcher->dispatch($event);

        return $page;
    }
}
