<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntitySearchResultLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @template TEntityCollection of EntityCollection
 *
 * @extends EntitySearchResultLoadedEvent<TEntityCollection>
 */
class ChannelEntitySearchResultLoadedEvent extends EntitySearchResultLoadedEvent implements ContenaChannelEvent
{
    /**
     * @param EntitySearchResult<TEntityCollection> $result
     */
    public function __construct(
        EntityDefinition $definition,
        EntitySearchResult $result,
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
