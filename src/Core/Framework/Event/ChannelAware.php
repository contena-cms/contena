<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\Framework\Log\Package;

#[IsFlowEventAware]
interface ChannelAware
{
    public function getChannelId(): string;
}
