Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'country',
    roles: {
        viewer: {
            privileges: [
                'country:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
                'user_config:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'country:update',
            ],
            dependencies: [
                'country.viewer',
            ],
        },
        creator: {
            privileges: [
                'country:create',
            ],
            dependencies: [
                'country.viewer',
                'country.editor',
            ],
        },
        deleter: {
            privileges: [
                'country:delete',
            ],
            dependencies: [
                'country.viewer',
            ],
        },
    },
});
