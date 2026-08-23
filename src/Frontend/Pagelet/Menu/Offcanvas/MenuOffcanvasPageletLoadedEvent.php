<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Menu\Offcanvas;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class MenuOffcanvasPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected MenuOffcanvasPagelet $pagelet,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPagelet(): MenuOffcanvasPagelet
    {
        return $this->pagelet;
    }
}
