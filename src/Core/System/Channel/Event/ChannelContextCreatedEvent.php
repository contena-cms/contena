<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class ChannelContextCreatedEvent extends Event implements ContenaChannelEvent
{
    /**
     * @param array<string, mixed> $session
     */
    public function __construct(
        private readonly ChannelContext $channelContext,
        private readonly string $usedToken,
        private readonly array $session = []
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

    /**
     * @return array<string, mixed>
     */
    public function getSession(): array
    {
        return $this->session;
    }
}
