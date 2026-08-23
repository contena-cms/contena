<?php declare(strict_types=1);

namespace Contena\Core\System\Organization\Aggregate\OrganizationTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<OrganizationTranslationEntity>
 */
class OrganizationTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return OrganizationTranslationEntity::class;
    }
}
