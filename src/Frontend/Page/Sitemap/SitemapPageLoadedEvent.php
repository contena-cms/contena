<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Sitemap;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class SitemapPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected SitemapPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): SitemapPage
    {
        return $this->page;
    }
}
