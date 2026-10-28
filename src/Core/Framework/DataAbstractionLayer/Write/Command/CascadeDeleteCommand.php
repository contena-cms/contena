<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Write\Command;

use Contena\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class CascadeDeleteCommand extends DeleteCommand
{
    public function isValid(): bool
    {
        // prevent execution
        return false;
    }

    public function getPrivilege(): ?string
    {
        return null;
    }
}
