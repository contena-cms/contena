<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

use Contena\Core\Content\Blog\Channel\Listing\BlogListingResult;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class BlogListingResultEvent extends NestedEvent implements ContenaChannelEvent
{
    public function __construct(
        protected Request $request,
        protected BlogListingResult $result,
        protected ChannelContext $context,
    ) {
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getContext(): Context
    {
        return $this->context->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->context;
    }

    public function getResult(): BlogListingResult
    {
        return $this->result;
    }
}
