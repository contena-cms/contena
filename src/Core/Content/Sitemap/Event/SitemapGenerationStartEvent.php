<?php declare(strict_types=1);

namespace Contena\Core\Content\Sitemap\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class SitemapGenerationStartEvent extends Event implements ContenaEvent
{
    public function __construct(
        private readonly ChannelContext $channelContext,
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
}
