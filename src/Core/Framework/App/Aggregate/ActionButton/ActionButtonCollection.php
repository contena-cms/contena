<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Aggregate\ActionButton;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;
use Contena\Core\Framework\Log\Package;

/**
 * @internal only for use by the app-system
 *
 * @extends EntityCollection<ActionButtonEntity>
 *
 * @codeCoverageIgnore
 */
#[Package('framework')]
class ActionButtonCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return ActionButtonEntity::class;
    }
}
