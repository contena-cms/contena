<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Navigation;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class NavigationPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(protected NavigationPage $page, ChannelContext $channelContext, Request $request)
    {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): NavigationPage
    {
        return $this->page;
    }
}
