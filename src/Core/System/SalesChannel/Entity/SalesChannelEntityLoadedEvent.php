<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\Entity;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @template TEntity of Entity
 *
 * @extends EntityLoadedEvent<TEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelEntityLoadedEvent extends EntityLoadedEvent implements ContenaSalesChannelEvent
{
    private readonly SalesChannelContext $salesChannelContext;

    /**
     * @param TEntity[] $entities
     */
    public function __construct(
        EntityDefinition $definition,
        array $entities,
        SalesChannelContext $context
    ) {
        parent::__construct($definition, $entities, $context->getContext());
        $this->salesChannelContext = $context;
    }

    public function getName(): string
    {
        return 'sales_channel.' . parent::getName();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
