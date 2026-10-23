<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

use Contena\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @codeCoverageIgnore
 */
class BlogSliderStreamCriteriaEvent implements ContenaChannelEvent
{
    public function __construct(
        public readonly CmsSlotEntity $slot,
        public readonly Criteria $criteria,
        private readonly ChannelContext $channelContext,
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
