<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Blog;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class BlogPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected BlogPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): BlogPage
    {
        return $this->page;
    }
}
