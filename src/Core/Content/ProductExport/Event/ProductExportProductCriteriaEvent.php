<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Event;

use Contena\Core\Content\ProductExport\ProductExportEntity;
use Contena\Core\Content\ProductExport\Struct\ExportBehavior;
use Contena\Core\Framework\Context;
use Contena\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Contena\Core\Framework\Event\NestedEvent;
use Contena\Core\Framework\Event\ContenaSalesChannelEvent;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelContext;

/**
 * @codeCoverageIgnore
 */
#[Package('inventory')]
class ProductExportProductCriteriaEvent extends NestedEvent implements ContenaSalesChannelEvent
{
    public function __construct(
        protected Criteria $criteria,
        protected ProductExportEntity $productExport,
        protected ExportBehavior $exportBehaviour,
        protected SalesChannelContext $salesChannelContext
    ) {
    }

    public function getCriteria(): Criteria
    {
        return $this->criteria;
    }

    public function getProductExport(): ProductExportEntity
    {
        return $this->productExport;
    }

    public function getExportBehaviour(): ExportBehavior
    {
        return $this->exportBehaviour;
    }

    public function getContext(): Context
    {
        return $this->salesChannelContext->getContext();
    }

    public function getSalesChannelContext(): SalesChannelContext
    {
        return $this->salesChannelContext;
    }
}
