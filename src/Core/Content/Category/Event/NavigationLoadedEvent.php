<?php declare(strict_types=1);

namespace Contena\Core\Content\Category\Event;

use Contena\Core\Content\Category\Tree\Tree;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;

class NavigationLoadedEvent extends NestedEvent implements ContenaChannelEvent
{
    public function __construct(
        protected Tree $navigation,
        protected ChannelContext $channelContext,
    ) {
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getNavigation(): Tree
    {
        return $this->navigation;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }
}
