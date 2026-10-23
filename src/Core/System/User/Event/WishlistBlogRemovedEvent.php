<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @codeCoverageIgnore
 */
class WishlistBlogRemovedEvent implements ContenaChannelEvent
{
    public function __construct(
        protected string $wishlistId,
        protected string $blogId,
        protected ChannelContext $context
    ) {
    }

    public function getWishlistId(): string
    {
        return $this->wishlistId;
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->context;
    }
}
