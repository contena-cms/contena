<?php declare(strict_types=1);

namespace Contena\Core\System\Region\Aggregate\RegionTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<RegionTranslationEntity>
 */
class RegionTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'region_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return RegionTranslationEntity::class;
    }
}
