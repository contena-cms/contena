<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

use Contena\Core\Content\Blog\Channel\CrossSelling\CrossSellingElementCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class BlogCrossSellingsLoadedEvent extends Event implements ContenaChannelEvent
{
    public function __construct(
        private readonly CrossSellingElementCollection $result,
        private readonly ChannelContext $channelContext
    ) {
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getCrossSellings(): CrossSellingElementCollection
    {
        return $this->result;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }
}
