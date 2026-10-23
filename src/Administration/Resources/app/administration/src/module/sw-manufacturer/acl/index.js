/*
 * @sw-package inventory
 */

Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues',
    key: 'blog_manufacturer',
    roles: {
        viewer: {
            privileges: [
                'blog_manufacturer:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
                Contena.Service('privileges').getPrivileges('media.viewer'),
                'admin_user_config:read',
                'admin_user_config:create',
                'admin_user_config:update',
            ],
            dependencies: [],
        },
        editor: {
            privileges: ['blog_manufacturer:update', Contena.Service('privileges').getPrivileges('media.creator')],
            dependencies: ['blog_manufacturer.viewer'],
        },
        creator: {
            privileges: ['blog_manufacturer:create'],
            dependencies: ['blog_manufacturer.viewer', 'blog_manufacturer.editor'],
        },
        deleter: {
            privileges: ['blog_manufacturer:delete'],
            dependencies: ['blog_manufacturer.viewer'],
        },
    },
});
