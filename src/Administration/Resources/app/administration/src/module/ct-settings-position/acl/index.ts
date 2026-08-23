Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'position',
    roles: {
        viewer: {
            privileges: [
                'position:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
                'user_config:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: ['position:update'],
            dependencies: ['position.viewer'],
        },
        creator: {
            privileges: ['position:create'],
            dependencies: ['position.viewer'],
        },
        deleter: {
            privileges: ['position:delete'],
            dependencies: ['position.viewer'],
        },
    },
});
