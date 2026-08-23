<?php declare(strict_types=1);

namespace Contena\Core\System\Position\Aggregate\PositionTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<PositionTranslationEntity>
 */
class PositionTranslationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'position_translation_collection';
    }

    protected function getExpectedClass(): string
    {
        return PositionTranslationEntity::class;
    }
}
