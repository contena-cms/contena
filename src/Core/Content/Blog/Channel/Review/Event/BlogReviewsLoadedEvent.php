<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Review\Event;

use Contena\Core\Content\Blog\Channel\Review\BlogReviewResult;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @codeCoverageIgnore
 */
final class BlogReviewsLoadedEvent extends NestedEvent implements ContenaChannelEvent
{
    public function __construct(
        public BlogReviewResult $reviews,
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
