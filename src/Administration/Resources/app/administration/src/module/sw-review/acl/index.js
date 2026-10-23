/**
 * @sw-package after-sales
 */
Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'catalogues',
    key: 'review',
    roles: {
        viewer: {
            privileges: [
                'blog_review:read',
                'user:read',
                'blog:read',
                'channel:read',
                'admin_user_config:read',
                'admin_user_config:create',
                'admin_user_config:update',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: ['blog_review:update'],
            dependencies: ['review.viewer'],
        },
        creator: {
            privileges: ['blog_review:create'],
            dependencies: ['review.viewer', 'review.editor'],
        },
        deleter: {
            privileges: ['blog_review:delete'],
            dependencies: ['review.viewer'],
        },
    },
});
