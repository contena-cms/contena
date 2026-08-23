<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Navigation\Error;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class ErrorPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected ErrorPage $page,
        ChannelContext $channelContext,
        Request $request
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): ErrorPage
    {
        return $this->page;
    }
}
