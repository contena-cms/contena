<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Region;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class RegionDataPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected RegionDataPagelet $pagelet,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPagelet(): RegionDataPagelet
    {
        return $this->pagelet;
    }
}
