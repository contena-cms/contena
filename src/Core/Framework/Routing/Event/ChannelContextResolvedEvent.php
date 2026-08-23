<?php declare(strict_types=1);

namespace Contena\Core\Framework\Routing\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class ChannelContextResolvedEvent extends Event implements ContenaChannelEvent
{
    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly string $usedToken
    ) {
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getUsedToken(): string
    {
        return $this->usedToken;
    }
}
