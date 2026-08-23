<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Search;

use Contena\Core\Content\Blog\Channel\Search\AbstractBlogSearchRoute;
use Contena\Core\Content\Category\Exception\CategoryNotFoundException;
use Contena\Core\Framework\Adapter\Translation\AbstractTranslator;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Routing\RoutingException;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a channel-api route to get or put data.
 */
class SearchPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly AbstractBlogSearchRoute $blogSearchRoute,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractTranslator $translator
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, ChannelContext $channelContext): SearchPage
    {
        $page = $this->genericLoader->load($request, $channelContext);
        $page = SearchPage::createFrom($page);
        $this->setMetaInformation($page);

        $criteria = new Criteria();
        $criteria->setTitle('search-page');

        $result = $this->blogSearchRoute
            ->load($request, $channelContext, $criteria)
            ->getListingResult();

        $page->setListing($result);

        $page->setSearchTerm(
            $request->query->getString('search')
        );

        $this->eventDispatcher->dispatch(
            new SearchPageLoadedEvent($page, $channelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(SearchPage $page): void
    {
        $page->getMetaInformation()?->setRobots('noindex,follow');
        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('search.metaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }
}
