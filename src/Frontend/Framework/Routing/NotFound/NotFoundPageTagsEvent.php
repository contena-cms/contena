<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing\NotFound;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NotFoundPageTagsEvent implements ContenaEvent
{
    /**
     * @param array<string> $tags
     */
    public function __construct(
        private array $tags,
        private readonly Request $request,
        private readonly ChannelContext $context
    ) {
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->context;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return array<string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param array<string> $tags
     */
    public function addTags(array $tags): void
    {
        $this->tags = array_merge($this->tags, $tags);
    }
}
