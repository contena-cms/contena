<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\MemberGroupRegistration;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class MemberGroupRegistrationPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected MemberGroupRegistrationPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): MemberGroupRegistrationPage
    {
        return $this->page;
    }
}
