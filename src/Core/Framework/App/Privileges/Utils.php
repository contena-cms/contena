<?php declare(strict_types=1);

namespace Contena\Core\Framework\App\Privileges;

use Contena\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Contena\Core\Framework\Store\Struct\PermissionCollection;
use Contena\Core\Framework\Store\Struct\PermissionStruct;

/**
 * @internal
 */
class Utils
{
    /**
     * @param array<string> $appPrivileges
     *
     * @return list<array<'entity'|'operation', string>>
     */
    public static function makePermissions(array $appPrivileges): array
    {
        $permissions = [];

        foreach ($appPrivileges as $privilege) {
            if (self::isCrudPrivilege($privilege)) {
                $entityAndOperation = explode(':', $privilege);
                if (\array_key_exists($entityAndOperation[1], AclRoleDefinition::PRIVILEGE_DEPENDENCE)) {
                    $permissions[] = array_combine(['entity', 'operation'], $entityAndOperation);

                    continue;
                }
            }

            $permissions[] = ['entity' => 'additional_privileges', 'operation' => $privilege];
        }

        return $permissions;
    }

    /**
     * @param array<string> $privileges
     *
     * @return array<string, PermissionCollection>
     */
    public static function makeCategorizedPermissions(array $privileges): array
    {
        $permissions = self::makePermissions($privileges);

        $permissionCollection = new PermissionCollection();

        foreach ($permissions as $permission) {
            $permissionCollection->add(PermissionStruct::fromArray([
                'entity' => $permission['entity'],
                'operation' => $permission['operation'],
            ]));
        }

        return $permissionCollection->getCategorizedPermissions();
    }

    private static function isCrudPrivilege(string $privilege): bool
    {
        return substr_count($privilege, ':') === 1;
    }
}
