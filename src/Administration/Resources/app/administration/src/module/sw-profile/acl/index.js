/**
 * @sw-package fundamentals@framework
 */
Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'admin_user',
    roles: {
        update_profile: {
            privileges: [
                'admin_user_change_me',
                'admin_user:read',
                'admin_user_config:read',
                'admin_user_config:update',
                'admin_user_config:create',
            ],
            dependencies: [],
        },
    },
});
