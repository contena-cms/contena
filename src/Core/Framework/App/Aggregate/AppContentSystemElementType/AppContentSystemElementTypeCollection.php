<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppContentSystemElementType;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentSystemElementTypeEntity>
 */
class AppContentSystemElementTypeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentSystemElementTypeEntity::class;
    }
}
