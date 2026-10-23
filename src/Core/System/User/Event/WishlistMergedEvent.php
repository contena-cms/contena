<?php declare(strict_types=1);

namespace Contena\Core\System\User\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class WishlistMergedEvent extends Event implements ContenaChannelEvent
{
    /**
     * @param array<array{id: string, blogId?: string, blogVersionId?: string}> $blogs
     */
    public function __construct(
        protected array $blogs,
        protected ChannelContext $context
    ) {
    }

    /**
     * @return array<array{id: string, blogId?: string, blogVersionId?: string}>
     */
    public function getBlogs(): array
    {
        return $this->blogs;
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
