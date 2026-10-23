<?php declare(strict_types=1);

namespace Contena\Core\System\Channel\Aggregate\ChannelAnalytics;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<ChannelAnalyticsEntity>
 *
 * @codeCoverageIgnore
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
