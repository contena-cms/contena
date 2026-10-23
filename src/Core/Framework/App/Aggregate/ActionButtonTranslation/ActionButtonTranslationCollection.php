<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\ActionButtonTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal
 *
 * @extends EntityCollection<ActionButtonTranslationEntity>
 *
 * @codeCoverageIgnore
 */
class ActionButtonTranslationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ActionButtonTranslationEntity::class;
    }
}
