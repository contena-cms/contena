<?php declare(strict_types=1);

namespace Contena\Core\System\Organization\Aggregate\OrganizationUnitTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OrganizationUnitTranslationEntity>
 */
class OrganizationUnitTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrganizationUnitTranslationEntity::class;
    }
}
