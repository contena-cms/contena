<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Validation\DataBag\DataBag;
use Contena\Core\System\Channel\ChannelContext;

class ChannelContextSwitchEvent extends NestedEvent implements ContenaChannelEvent
{
    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly DataBag $requestDataBag
    ) {
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getRequestDataBag(): DataBag
    {
        return $this->requestDataBag;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }
}
