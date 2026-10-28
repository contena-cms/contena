<?php declare(strict_types=1);

namespace Contena\Core\Framework\Script;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<ScriptEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ScriptCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ScriptEntity::class;
    }
}
