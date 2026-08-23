<?php declare(strict_types=1);

namespace Contena\Core\System\Organization\Aggregate\OrganizationUnit;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OrganizationUnitEntity>
 */
class OrganizationUnitCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrganizationUnitEntity::class;
    }
}
