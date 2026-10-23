<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script\Execution\Awareness;

use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
trait ChannelContextAwareTrait
{
    protected ChannelContext $channelContext;

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }
}
