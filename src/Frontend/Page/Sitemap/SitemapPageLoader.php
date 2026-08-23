<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Sitemap;

use Contena\Core\Content\Sitemap\Channel\AbstractSitemapRoute;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a channel-api route to get or put data.
 */
class SitemapPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly AbstractSitemapRoute $sitemapRoute
    ) {
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, ChannelContext $context): SitemapPage
    {
        $page = new SitemapPage();
        $page->setSitemaps($this->sitemapRoute->load($request, $context)->getSitemaps()->getElements());

        $this->eventDispatcher->dispatch(
            new SitemapPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
