<?php declare(strict_types=1);

namespace Contena\Frontend\Event\RouteRequest;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;

abstract class RouteRequestEvent extends NestedEvent implements ContenaChannelEvent
{
    private readonly Criteria $criteria;

    public function __construct(
        private readonly Request $frontendRequest,
        private readonly Request $channelApiRequest,
        private readonly ChannelContext $channelContext,
        ?Criteria $criteria = null,
    ) {
        $this->criteria = $criteria ?? new Criteria();
    }

    public function getFrontendRequest(): Request
    {
        return $this->frontendRequest;
    }

    public function getChannelApiRequest(): Request
    {
        return $this->channelApiRequest;
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }

    public function getContext(): Context
    {
        return $this->channelContext->getContext();
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }
}
