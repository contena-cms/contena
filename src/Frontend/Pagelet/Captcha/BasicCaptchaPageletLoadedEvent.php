<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Captcha;

use Contena\Core\System\Channel\ChannelContext;
use Contena\Frontend\Pagelet\PageletLoadedEvent;
use Symfony\Component\HttpFoundation\Request;

class BasicCaptchaPageletLoadedEvent extends PageletLoadedEvent
{
    public function __construct(
        protected BasicCaptchaPagelet $pagelet,
        ChannelContext $channelContext,
        Request $request,
    ) {
        parent::__construct($channelContext, $request);
    }

    public function getPagelet(): BasicCaptchaPagelet
    {
        return $this->pagelet;
    }
}
