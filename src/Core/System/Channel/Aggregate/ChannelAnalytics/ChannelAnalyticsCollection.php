<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelAnalytics;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<ChannelAnalyticsEntity>
 */
class ChannelAnalyticsCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'channel_analytics_collection';
    }

    protected function getExpectedClass(): string
    {
        return ChannelAnalyticsEntity::class;
    }
}
