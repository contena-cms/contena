<?php declare(strict_types=1);

namespace Contena\Core\System\Region\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\Event;

class RegionCriteriaEvent extends Event implements ContenaChannelEvent
{
    public function __construct(
        private readonly string $countryId,
        private readonly Request $request,
        private readonly Criteria $criteria,
        private readonly ChannelContext $channelContext,
    ) {
    }

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function getRequest(): Request
    {
        return $this->request;
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
}
