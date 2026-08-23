<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\RecoverPassword;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class AccountRecoverPasswordPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountRecoverPasswordPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): AccountRecoverPasswordPage
    {
        return $this->page;
    }
}
