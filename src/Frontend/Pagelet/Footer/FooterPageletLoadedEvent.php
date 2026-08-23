<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Footer;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class FooterPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected FooterPagelet $pagelet,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPagelet(): FooterPagelet
    {
        return $this->pagelet;
    }
}
