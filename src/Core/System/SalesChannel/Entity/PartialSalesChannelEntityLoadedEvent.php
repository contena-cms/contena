<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\PartialEntity;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @extends SalesChannelEntityLoadedEvent<PartialEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class PartialSalesChannelEntityLoadedEvent extends SalesChannelEntityLoadedEvent
{
    /**
     * @param PartialEntity[] $entities
     */
    public function __construct(
        EntityDefinition $definition,
        array $entities,
        SalesChannelContext $context
    ) {
        parent::__construct($definition, $entities, $context);

        $this->name = $this->definition->getEntityName() . '.partial_loaded';
    }
}
