<?php declare(strict_types=1);

namespace Contena\Core\Framework\Api\Acl\Role;

use Contena\Core\Framework\Log\Package;

class AclRoleEvents
{
    final public const ACL_ROLE_WRITTEN_EVENT = 'acl_role.written';

    final public const ACL_ROLE_DELETED_EVENT = 'acl_role.deleted';

    final public const ACL_ROLE_LOADED_EVENT = 'acl_role.loaded';

    final public const ACL_ROLE_SEARCH_RESULT_LOADED_EVENT = 'acl_role.search.result.loaded';
}
