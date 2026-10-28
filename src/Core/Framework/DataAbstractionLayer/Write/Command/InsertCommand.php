<?php declare(strict_types=1);

namespace Contena\Core\Framework\DataAbstractionLayer\Write\Command;

use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\Log\Package;

/**
 * @final
 */
#[Package('framework')]
class InsertCommand extends WriteCommand
{
    public function getPrivilege(): string
    {
        return AclRoleDefinition::PRIVILEGE_CREATE;
    }
}
