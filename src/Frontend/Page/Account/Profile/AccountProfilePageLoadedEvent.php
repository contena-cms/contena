<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Profile;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
class AccountProfilePageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountProfilePage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): AccountProfilePage
    {
        return $this->page;
    }
}
