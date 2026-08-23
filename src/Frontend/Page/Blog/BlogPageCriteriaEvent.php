<?php declare(strict_types=1);

namespace Contena\Frontend\Page\Blog;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

class BlogPageCriteriaEvent extends Event implements ContenaChannelEvent
{
    public function __construct(
        protected string $blogId,
        protected Criteria $criteria,
        protected ChannelContext $context
    ) {
    }

    public function getBlogId(): string
    {
        return $this->blogId;
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
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
