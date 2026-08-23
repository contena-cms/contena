Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'additional_permissions',
    parent: null,
    key: 'system',
    roles: {
        plugin_maintain: {
            privileges: [
                'system.plugin_maintain',
                'system.plugin_upload',
                'plugin:read',
                'plugin:create',
                'plugin:update',
                'plugin:delete',
                'plugin_translation:read',
                'plugin_translation:create',
                'plugin_translation:update',
                'plugin_translation:delete',
            ],
            dependencies: [],
        },
        extension_store: {
            privileges: [],
            dependencies: [
                'system.plugin_maintain',
            ],
        },
    },
});
