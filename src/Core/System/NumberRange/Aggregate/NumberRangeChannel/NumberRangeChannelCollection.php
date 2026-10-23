<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\Aggregate\NumberRangeChannel;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NumberRangeChannelEntity>
 *
 * @codeCoverageIgnore
 */
class NumberRangeChannelCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'number_range_channel_collection';
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeChannelEntity::class;
    }
}
