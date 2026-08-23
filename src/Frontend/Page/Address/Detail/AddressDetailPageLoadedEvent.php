<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Address\Detail;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class AddressDetailPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AddressDetailPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): AddressDetailPage
    {
        return $this->page;
    }
}
