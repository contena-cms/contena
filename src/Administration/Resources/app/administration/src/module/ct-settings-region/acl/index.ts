Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'region',
    roles: {
        viewer: {
            privileges: [
                'region:read',
                'country:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
                'user_config:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: ['region:update'],
            dependencies: ['region.viewer'],
        },
        creator: {
            privileges: ['region:create'],
            dependencies: ['region.viewer'],
        },
        deleter: {
            privileges: ['region:delete'],
            dependencies: ['region.viewer'],
        },
    },
});
