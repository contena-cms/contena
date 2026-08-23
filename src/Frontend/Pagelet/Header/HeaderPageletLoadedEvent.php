<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Header;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class HeaderPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected HeaderPagelet $pagelet,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPagelet(): HeaderPagelet
    {
        return $this->pagelet;
    }
}
