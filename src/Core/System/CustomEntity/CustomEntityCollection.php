<?php declare(strict_types=1);

namespace Contena\Core\System\CustomEntity;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<CustomEntityEntity>
 *
 * @codeCoverageIgnore
 */
class CustomEntityCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'custom_entity_collection';
    }

    protected function getExpectedClass(): string
    {
        return CustomEntityEntity::class;
    }
}
