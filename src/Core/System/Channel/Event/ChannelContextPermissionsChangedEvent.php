<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;

class ChannelContextPermissionsChangedEvent extends NestedEvent implements ContenaChannelEvent
{
    /**
     * @param array<string, bool> $permissions
     */
    public function __construct(
        private readonly ChannelContext $channelContext,
        protected array $permissions = []
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

    /**
     * @return array<string, bool>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }
}
