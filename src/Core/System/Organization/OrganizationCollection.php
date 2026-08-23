<?php declare(strict_types=1);

namespace Contena\Core\System\Organization;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OrganizationEntity>
 */
class OrganizationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrganizationEntity::class;
    }
}
