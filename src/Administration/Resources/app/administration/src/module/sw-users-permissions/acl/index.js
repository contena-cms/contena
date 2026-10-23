/**
 * @sw-package fundamentals@framework
 */
Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'users_and_permissions',
    roles: {
        viewer: {
            privileges: [
                'admin_user:read',
                'acl_role:read',
                'admin_user_access_key:read',
                'media:read',
                'media_default_folder:read',
                'integration:read',
                'app:read',
                'admin_user_config:read',
                'admin_user_config:create',
                'admin_user_config:update',
                'currency:read',
                'system_config:read',
                'api_acl_privileges_additional_get',
                'media_folder:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'admin_user:update',
                'acl_role:update',
                'admin_user_access_key:create',
                'admin_user_access_key:update',
                'admin_user_access_key:delete',
                'system_config:read',
                'system_config:create',
                'system_config:update',
                'system_config:delete',
                'api_action_user_mcp-allowlist',
            ],
            dependencies: ['users_and_permissions.viewer'],
        },
        creator: {
            privileges: ['admin_user:create', 'acl_role:create'],
            dependencies: ['users_and_permissions.viewer', 'users_and_permissions.editor'],
        },
        deleter: {
            privileges: ['admin_user:delete', 'acl_role:delete'],
            dependencies: ['users_and_permissions.viewer'],
        },
    },
});
