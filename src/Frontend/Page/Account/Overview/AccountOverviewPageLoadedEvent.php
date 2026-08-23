<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Overview;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountOverviewPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountOverviewPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): AccountOverviewPage
    {
        return $this->page;
    }
}
