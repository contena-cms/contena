<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\System\Channel\ChannelContext;

interface ContenaChannelEvent extends ContenaEvent
{
    public function getChannelContext(): ChannelContext;
}
