<?php declare(strict_types=1);

namespace Contena\Core\System\NumberRange\Aggregate\NumberRangeType;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<NumberRangeTypeEntity>
 */
class NumberRangeTypeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'number_range_type_collection';
    }

    protected function getExpectedClass(): string
    {
        return NumberRangeTypeEntity::class;
    }
}
