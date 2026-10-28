<?php declare(strict_types=1);

namespace Contena\Core\System\SalesChannel\Aggregate\SalesChannelType;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;
use Contena\Core\System\SalesChannel\SalesChannelCollection;

/**
 * @extends EntityCollection<SalesChannelTypeEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('discovery')]
class SalesChannelTypeCollection extends EntityCollection
{
    public function getSalesChannels(): SalesChannelCollection
    {
        return new SalesChannelCollection(
            $this->flatMap(static fn (SalesChannelTypeEntity $salesChannel) => $salesChannel->getSalesChannels())
        );
    }

    public function getApiAlias(): string
    {
        return 'sales_channel_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return SalesChannelTypeEntity::class;
    }
}
