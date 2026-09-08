<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<ScriptEntity>
 *
 * @codeCoverageIgnore
 */
class ScriptCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ScriptEntity::class;
    }
}
