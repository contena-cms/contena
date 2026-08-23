<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Suggest;

use Contena\Core\Content\Blog\Channel\Suggest\AbstractBlogSuggestRoute;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a Channel API route to get or put data.
 */
class SuggestPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractBlogSuggestRoute $blogSuggestRoute,
        private readonly GenericPageLoaderInterface $genericLoader
    ) {
    }

    public function load(Request $request, ChannelContext $channelContext): SuggestPage
    {
        $page = SuggestPage::createFrom($this->genericLoader->load($request, $channelContext));

        $criteria = new Criteria();
        $criteria->setLimit(10);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $page->setSearchResult(
            $this->blogSuggestRoute
                ->load($request, $channelContext, $criteria)
                ->getListingResult()
        );

        $page->setSearchTerm((string) $request->query->get('search'));

        $this->eventDispatcher->dispatch(
            new SuggestPageLoadedEvent($page, $channelContext, $request)
        );

        return $page;
    }
}
