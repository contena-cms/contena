<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Comment\Event;

use Contena\Core\Content\Blog\Channel\Comment\BlogCommentResult;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
final class BlogCommentsLoadedEvent extends NestedEvent implements ContenaChannelEvent
{
    public function __construct(
        public BlogCommentResult $comments,
        public Request $request,
        protected ChannelContext $channelContext,
    ) {
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }
}
