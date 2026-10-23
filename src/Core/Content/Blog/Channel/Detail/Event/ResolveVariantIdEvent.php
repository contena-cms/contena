<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Channel\Detail\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class ResolveVariantIdEvent extends Event implements ContenaChannelEvent
{
    public function __construct(
        private readonly string $blogId,
        private ?string $resolvedVariantId,
        private readonly ChannelContext $channelContext
    ) {
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function setResolvedVariantId(?string $resolvedVariantId): void
    {
        $this->resolvedVariantId = $resolvedVariantId;
    }

    public function getResolvedVariantId(): ?string
    {
        return $this->resolvedVariantId;
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
