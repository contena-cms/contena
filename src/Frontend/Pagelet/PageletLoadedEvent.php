<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
abstract class PageletLoadedEvent extends NestedEvent implements ContenaChannelEvent
{
    public function __construct(
        protected ChannelContext $channelContext,
        protected Request $request,
    ) {
    }

    /**
     * @return Pagelet
     */
    abstract public function getPagelet();

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
