<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\Aggregate\NumberRangeState;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<NumberRangeStateEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class NumberRangeStateCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'number_range_state_collection';
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeStateEntity::class;
    }
}
