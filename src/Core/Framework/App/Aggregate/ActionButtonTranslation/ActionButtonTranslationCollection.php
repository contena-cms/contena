<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\ActionButtonTranslation;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

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
