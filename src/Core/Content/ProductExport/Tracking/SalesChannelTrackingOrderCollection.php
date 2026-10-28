<?php declare(strict_types=1);

namespace Contena\Core\Content\ProductExport\Tracking;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:AGENTIC_AI_SALES_CHANNEL
 *
 * @extends EntityCollection<SalesChannelTrackingOrderEntity>
 */
#[Package('discovery')]
class SalesChannelTrackingOrderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return SalesChannelTrackingOrderEntity::class;
    }
}
