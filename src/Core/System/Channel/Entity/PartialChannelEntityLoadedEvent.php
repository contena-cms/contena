<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\System\Channel\ChannelContext;

/**
 * @extends ChannelEntityLoadedEvent<PartialEntity>
 */
class PartialChannelEntityLoadedEvent extends ChannelEntityLoadedEvent
{
    /**
     * @param PartialEntity[] $entities
     */
    public function __construct(
        EntityDefinition $definition,
        array $entities,
        ChannelContext $context
    ) {
        parent::__construct($definition, $entities, $context);

        $this->name = $this->definition->getEntityName() . '.partial_loaded';
    }
}
