<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

#[IsFlowEventAware]
interface ChannelAware
{
    public function getChannelId(): string;
}
