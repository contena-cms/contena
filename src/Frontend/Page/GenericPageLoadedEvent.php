<?php declare(strict_types=1);

namespace Contena\Frontend\Page;

use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class GenericPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(protected Page $page, ChannelContext $channelContext, Request $request)
    {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): Page
    {
        return $this->page;
    }
}
