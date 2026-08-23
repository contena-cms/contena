<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\Event\ContenaChannelEvent;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @template TEntity of Entity
 *
 * @extends EntityLoadedEvent<TEntity>
 */
class ChannelEntityLoadedEvent extends EntityLoadedEvent implements ContenaChannelEvent
{
    private readonly ChannelContext $channelContext;

    /**
     * @param TEntity[] $entities
     */
    public function __construct(
        EntityDefinition $definition,
        array $entities,
        ChannelContext $context
    ) {
        parent::__construct($definition, $entities, $context->getContext());
        $this->channelContext = $context;
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
