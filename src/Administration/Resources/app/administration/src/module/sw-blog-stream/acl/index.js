/**
 * @sw-package inventory
 */
Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues',
    key: 'blog_stream',
    roles: {
        viewer: {
            privileges: [
                'blog_stream:read',
                'blog_stream_filter:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
                'channel:read',
                'channel_type:read',
                'blog:read',
                'blog_manufacturer:read',
                'property_group_option:read',
                'property_group:read',
                'currency:read',
                'admin_user_config:read',
                'admin_user_config:create',
                'admin_user_config:update',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'blog_stream:update',
                'blog_stream_filter:update',
                'blog_stream_filter:delete',
                'blog_stream_filter:create',
            ],
            dependencies: ['blog_stream.viewer'],
        },
        creator: {
            privileges: ['blog_stream:create'],
            dependencies: ['blog_stream.viewer', 'blog_stream.editor'],
        },
        deleter: {
            privileges: ['blog_stream:delete'],
            dependencies: ['blog_stream.viewer'],
        },
    },
});
