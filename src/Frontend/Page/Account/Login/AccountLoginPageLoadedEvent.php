<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Login;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountLoginPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountLoginPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): AccountLoginPage
    {
        return $this->page;
    }
}
