<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\System\Channel\ChannelContext;

class ChannelContextRestoredEvent extends NestedEvent
{
    public function __construct(
        private readonly ChannelContext $restoredContext,
        private readonly ChannelContext $currentContext
    ) {
    }

    public function getRestoredChannelContext(): ChannelContext
    {
        return $this->restoredContext;
    }

    public function getContext(): Context
    {
        return $this->restoredContext->getContext();
    }

    public function getCurrentChannelContext(): ChannelContext
    {
        return $this->currentContext;
    }
}
