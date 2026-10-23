<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Account\Order;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
class AccountOrderPageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected AccountOrderPage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): AccountOrderPage
    {
        return $this->page;
    }
}
