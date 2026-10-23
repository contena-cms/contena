<?php declare(strict_types=1);

namespace Contena\Frontend\Event;

use Contena\Core\Content\Blog\Channel\ChannelBlogEntity;
use Contena\Core\Content\Property\PropertyGroupCollection;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
class SwitchBuyBoxVariantEvent extends Event implements ContenaChannelEvent
{
    public function __construct(
        private readonly string $elementId,
        private readonly ChannelBlogEntity $blog,
        private readonly ?PropertyGroupCollection $configurator,
        private readonly Request $request,
        private readonly ChannelContext $channelContext
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getElementId(): string
    {
        return $this->elementId;
    }

    public function getBlog(): ChannelBlogEntity
    {
        return $this->blog;
    }

    public function getConfigurator(): ?PropertyGroupCollection
    {
        return $this->configurator;
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
