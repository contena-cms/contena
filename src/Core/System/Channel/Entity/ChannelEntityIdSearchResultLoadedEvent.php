<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityIdSearchResultLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;

class ChannelEntityIdSearchResultLoadedEvent extends EntityIdSearchResultLoadedEvent implements ContenaChannelEvent
{
    public function __construct(
        EntityDefinition $definition,
        IdSearchResult $result,
        private readonly ChannelContext $channelContext
    ) {
        parent::__construct($definition, $result);
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
