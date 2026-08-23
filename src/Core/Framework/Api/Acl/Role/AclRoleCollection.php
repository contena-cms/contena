<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Acl\Role;

use Contena\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<AclRoleEntity>
 */
class AclRoleCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_acl_role_collection';
    }

    protected function getExpectedClass(): string
    {
        return AclRoleEntity::class;
    }
}
