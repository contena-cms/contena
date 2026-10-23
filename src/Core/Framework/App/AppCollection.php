<?php declare(strict_types=1);

namespace Contena\Core\Framework\App;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<AppEntity>
 *
 * @codeCoverageIgnore
 */
class AppCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppEntity::class;
    }
}
