<?php

declare(strict_types=1);

namespace Contena\Core\Content\Cookie\Event;

use Contena\Core\Content\Cookie\Struct\CookieGroupCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class CookieGroupCollectEvent implements ContenaChannelEvent
{
    public function __construct(
        public CookieGroupCollection $cookieGroupCollection,
        public readonly Request $request,
        protected readonly ChannelContext $channelContext,
    ) {
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }
}
