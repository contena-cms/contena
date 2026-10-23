/**
 * @sw-package discovery
 */

Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'user_groups',
    roles: {
        viewer: {
            privileges: [
                'user_group:read',
                'channel:read',
                'user:read',
                'seo_url:read',
                'channel_domain:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'user_group:update',
                'user_group_registration_channels:create',
                'user_group_registration_channels:delete',
            ],
            dependencies: ['user_groups.viewer'],
        },
        creator: {
            privileges: ['user_group:create'],
            dependencies: ['user_groups.viewer', 'user_groups.editor'],
        },
        deleter: {
            privileges: ['user_group:delete', 'seo_url:delete'],
            dependencies: ['user_groups.viewer'],
        },
    },
});
