<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Maintenance;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class MaintenancePageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected MaintenancePage $page,
        ChannelContext $channelContext,
        Request $request
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): MaintenancePage
    {
        return $this->page;
    }
}
