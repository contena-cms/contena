<?php declare(strict_types=1);

namespace Contena\Frontend\Page\LandingPage;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class LandingPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(protected LandingPage $page, ChannelContext $channelContext, Request $request)
    {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): LandingPage
    {
        return $this->page;
    }
}
