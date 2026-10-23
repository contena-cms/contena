/**
 * @sw-package inventory
 */
Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues',
    key: 'content_stream',
    roles: {
        viewer: {
            privileges: [
                'content_stream:read',
                'content_stream_filter:read',
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
                'content_stream:update',
                'content_stream_filter:update',
                'content_stream_filter:delete',
                'content_stream_filter:create',
            ],
            dependencies: ['content_stream.viewer'],
        },
        creator: {
            privileges: ['content_stream:create'],
            dependencies: ['content_stream.viewer', 'content_stream.editor'],
        },
        deleter: {
            privileges: ['content_stream:delete'],
            dependencies: ['content_stream.viewer'],
        },
    },
});
