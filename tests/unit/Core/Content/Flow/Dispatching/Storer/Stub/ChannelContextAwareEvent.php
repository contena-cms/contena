<?php declare(strict_types=1);

namespace Contena\Tests\Unit\Core\Content\Flow\Dispatching\Storer\Stub;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ChannelContextAware;
use Contena\Core\Framework\Event\EventData\EventDataCollection;
use Contena\Core\Framework\Event\FlowEventAware;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @internal
 */
class ChannelContextAwareEvent implements FlowEventAware, ChannelContextAware
{
    public function __construct(
        private readonly string $channelId,
        private readonly ChannelContext $channelContext,
    ) {
    }

    public function getChannelId(): string
    {
        return $this->channelId;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getName(): string
    {
        return 'test';
    }

    public function getContext(): Context
    {
        return Context::createDefaultContext();
    }

    public static function getAvailableData(): EventDataCollection
    {
        return new EventDataCollection();
    }
}
