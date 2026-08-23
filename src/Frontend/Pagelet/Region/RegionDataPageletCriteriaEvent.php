<?php declare(strict_types=1);

namespace Contena\Frontend\Pagelet\Region;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

class RegionDataPageletCriteriaEvent extends NestedEvent implements ContenaChannelEvent
{
    public function __construct(
        private readonly Criteria $criteria,
        private readonly ChannelContext $channelContext,
        private readonly Request $request,
    ) {
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }
}
