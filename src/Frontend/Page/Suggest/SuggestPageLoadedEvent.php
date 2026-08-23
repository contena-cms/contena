<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Suggest;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class SuggestPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected SuggestPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): SuggestPage
    {
        return $this->page;
    }
}
