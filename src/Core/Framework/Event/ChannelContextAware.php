<?php declare(strict_types=1);

namespace Contena\Core\Framework\Event;

use Contena\Core\System\Channel\ChannelContext;

/**
 * @codeCoverageIgnore
 */
#[IsFlowEventAware]
interface ChannelContextAware extends ChannelAware
{
    public const CHANNEL_CONTEXT = 'channelContext';

    public const CHANNEL_DOMAIN_ID = 'channelDomainId';

    public const CHANNEL_MEMBER_ID = 'channelMemberId';

    public function getChannelContext(): ChannelContext;
}
