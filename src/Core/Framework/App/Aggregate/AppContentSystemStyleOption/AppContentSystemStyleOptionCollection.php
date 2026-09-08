<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppContentSystemStyleOption;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentSystemStyleOptionEntity>
 */
class AppContentSystemStyleOptionCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentSystemStyleOptionEntity::class;
    }
}
