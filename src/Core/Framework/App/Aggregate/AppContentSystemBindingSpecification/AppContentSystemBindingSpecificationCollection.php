<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\AppContentSystemBindingSpecification;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @extends EntityCollection<AppContentSystemBindingSpecificationEntity>
 */
class AppContentSystemBindingSpecificationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return AppContentSystemBindingSpecificationEntity::class;
    }
}
