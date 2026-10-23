<?php declare(strict_types=1);

namespace Contena\Core\Content\Blog\Events;

use Contena\Core\Content\Blog\Aggregate\BlogCrossSelling\BlogCrossSellingEntity;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\Channel\ChannelContext;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @codeCoverageIgnore
 */
abstract class BlogCrossSellingCriteriaEvent extends Event implements ContenaChannelEvent
{
    public function __construct(
        private readonly BlogCrossSellingEntity $crossSelling,
        private readonly Criteria $criteria,
        private readonly ChannelContext $channelContext
    ) {
    }

    public function getCrossSelling(): BlogCrossSellingEntity
    {
        return $this->crossSelling;
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
