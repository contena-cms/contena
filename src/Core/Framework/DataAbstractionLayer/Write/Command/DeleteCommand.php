<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Write\Command;

use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Contena\Core\Framework\DataAbstractionLayer\Write\EntityExistence;

/**
 * @phpstan-ignore-next-line cannot be final, as it is extended, also designed to be used directly
 */
class DeleteCommand extends WriteCommand implements ChangeSetAware
{
    use ChangeSetAwareTrait;

    /**
     * @param array<string, string> $primaryKey
     */
    public function __construct(
        EntityDefinition $definition,
        array $primaryKey,
        EntityExistence $existence,
    ) {
        parent::__construct($definition, [], $primaryKey, $existence, '');
    }

    public function isValid(): bool
    {
        return (bool) \count($this->primaryKey);
    }

    public function getPrivilege(): ?string
    {
        return AclRoleDefinition::PRIVILEGE_DELETE;
    }
}
