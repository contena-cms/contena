<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<SalesChannelAnalyticsEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelAnalyticsCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'sales_channel_analytics_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelAnalyticsEntity::class;
    }
}
