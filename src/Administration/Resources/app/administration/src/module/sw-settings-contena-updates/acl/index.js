/**
 * @sw-package framework
 */
Contena.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'system',
        roles: {
            core_update: {
                privileges: ['system:core:update', 'system_config:read'],
                dependencies: [],
            },
        },
    })
    .addPrivilegeMappingEntry({
        category: 'additional_permissions',
        parent: null,
        key: 'system',
        roles: {
            plugin_maintain: {
                privileges: [
                    'system:plugin:maintain',
                    'plugin:update',
                    'system:clear:cache',
                    'system_config:read',
                    'system:app:change',
                ],
                dependencies: [],
            },
            plugin_upload: {
                privileges: ['admin_user_config:read', 'admin_user_config:update', 'admin_user_config:create'],
                dependencies: ['system.plugin_maintain'],
            },
        },
    });
