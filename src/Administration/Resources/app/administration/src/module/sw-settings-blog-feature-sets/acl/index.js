/**
 * @sw-package inventory
 */
Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'blog_feature_sets',
    roles: {
        viewer: {
            privileges: [
                'blog_feature_set:read',
                'custom_field:read',
                'property_group:read',
                'admin_user_config:read',
                'admin_user_config:create',
                'admin_user_config:update',
            ],
            dependencies: [],
        },
        editor: {
            privileges: ['blog_feature_set:update'],
            dependencies: ['blog_feature_sets.viewer'],
        },
        creator: {
            privileges: ['blog_feature_set:create'],
            dependencies: ['blog_feature_sets.viewer', 'blog_feature_sets.editor'],
        },
        deleter: {
            privileges: ['blog_feature_set:delete'],
            dependencies: ['blog_feature_sets.viewer'],
        },
    },
});
