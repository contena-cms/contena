<?php declare(strict_types=1);

namespace Contena\Storefront\Page\Sitemap;

use Contena\Core\Content\Sitemap\SalesChannel\AbstractSitemapRoute;
use Contena\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('discovery')]
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
    public function load(Request $request, SalesChannelContext $context): SitemapPage
    {
        $page = new SitemapPage();
        $page->setSitemaps($this->sitemapRoute->load($request, $context)->getSitemaps()->getElements());

        $this->eventDispatcher->dispatch(
            new SitemapPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }
}
