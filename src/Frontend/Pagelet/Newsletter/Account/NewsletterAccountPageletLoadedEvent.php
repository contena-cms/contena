<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Newsletter\Account;

use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
class NewsletterAccountPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected NewsletterAccountPagelet $pagelet,
        ChannelContext $channelContext,
        Request $request
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPagelet(): NewsletterAccountPagelet
    {
        return $this->pagelet;
    }
}
