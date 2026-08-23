<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;

class ChannelEntityAggregationResultLoadedEvent extends EntityAggregationResultLoadedEvent implements ContenaChannelEvent
{
    private readonly ChannelContext $channelContext;

    public function __construct(
        EntityDefinition $definition,
        AggregationResultCollection $result,
        ChannelContext $channelContext
    ) {
        parent::__construct($definition, $result, $channelContext->getContext());
        $this->channelContext = $channelContext;
    }

    public function getName(): string
    {
        return 'channel.' . parent::getName();
    }

    public function getChannelContext(): ChannelContext
    {
        return $this->channelContext;
    }
}
