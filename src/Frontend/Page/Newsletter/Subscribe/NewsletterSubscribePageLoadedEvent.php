<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Newsletter\Subscribe;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Page\PageLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
class NewsletterSubscribePageLoadedEvent extends PageLoadedEvent
{
    public function __construct(
        protected NewsletterSubscribePage $page,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPage(): NewsletterSubscribePage
    {
        return $this->page;
    }
}
