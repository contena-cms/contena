<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Write\Command;

/**
 * @final
 */
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
