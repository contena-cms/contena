<?php declare(strict_types=1);

namespace Contena\Frontend\Framework\Routing\NotFound;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\ContenaEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class NotFoundPageCacheKeyEvent implements ContenaEvent
{
    public function __construct(
        private string $key,
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

    public function getKey(): string
    {
        return $this->key;
    }

    public function setKey(string $key): void
    {
        $this->key = $key;
    }
}
