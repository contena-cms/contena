<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Entity;

use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Event\EntityAggregationResultLoadedEvent;
use Contena\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelEntityAggregationResultLoadedEvent extends EntityAggregationResultLoadedEvent implements ContenaSalesChannelEvent
{
    private readonly SalesChannelContext $salesChannelContext;

    public function __construct(
        EntityDefinition $definition,
        AggregationResultCollection $result,
        SalesChannelContext $salesChannelContext
    ) {
        parent::__construct($definition, $result, $salesChannelContext->getContext());
        $this->salesChannelContext = $salesChannelContext;
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
