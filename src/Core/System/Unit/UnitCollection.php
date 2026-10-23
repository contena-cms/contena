<?php declare(strict_types=1);

namespace Contena\Core\System\Unit;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UnitEntity>
 *
 * @codeCoverageIgnore
 */
class UnitCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'unit_collection';
    }

    protected function getExpectedClass(): string
    {
        return UnitEntity::class;
    }
}
