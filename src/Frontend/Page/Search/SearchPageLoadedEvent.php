<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Search;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class SearchPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected SearchPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): SearchPage
    {
        return $this->page;
    }
}
