<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppContentSystemLayoutPreset;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentSystemLayoutPresetEntity>
 */
class AppContentSystemLayoutPresetCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentSystemLayoutPresetEntity::class;
    }
}
