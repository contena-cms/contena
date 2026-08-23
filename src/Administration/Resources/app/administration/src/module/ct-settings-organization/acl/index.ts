Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'organization',
    roles: {
        viewer: {
            privileges: [
                'organization:read',
                'organization_unit:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
                'user_config:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: ['organization:update'],
            dependencies: ['organization.viewer'],
        },
        creator: {
            privileges: ['organization:create'],
            dependencies: ['organization.viewer'],
        },
        deleter: {
            privileges: ['organization:delete'],
            dependencies: ['organization.viewer'],
        },
    },
});
