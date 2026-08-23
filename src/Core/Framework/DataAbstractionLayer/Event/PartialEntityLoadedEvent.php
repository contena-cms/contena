<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Event;

use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;

/**
 * @extends EntityLoadedEvent<PartialEntity>
 */
class PartialEntityLoadedEvent extends EntityLoadedEvent
{
    /**
     * @param PartialEntity[] $entities
     */
    public function __construct(
        EntityDefinition $definition,
        array $entities,
        Context $context
    ) {
        parent::__construct($definition, $entities, $context);
        $this->name = $this->definition->getEntityName() . '.partial_loaded';
    }
}
