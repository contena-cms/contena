Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'blog_search_config',
    roles: {
        viewer: {
            privileges: [
                'blog_search_config:read',
                'blog_search_config_field:read',
                'custom_field_set:read',
                'blog_search_keyword:read',
                'blog:read',
                'channel:read',
                'custom_field:read',
                'system:clear:cache',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'blog_search_config:update',
                'blog_search_config_field:update',
                'blog_search_keyword:update',
                'system:clear:cache',
            ],
            dependencies: ['blog_search_config.viewer'],
        },
        creator: {
            privileges: [
                'blog_search_config:create',
                'blog_search_config_field:create',
                'blog_search_keyword:create',
                'system:clear:cache',
            ],
            dependencies: [
                'blog_search_config.viewer',
                'blog_search_config.editor',
            ],
        },
        deleter: {
            privileges: [
                'blog_search_config:delete',
                'blog_search_config_field:delete',
                'blog_search_keyword:delete',
                'blog_search_config:update',
                'system:clear:cache',
            ],
            dependencies: ['blog_search_config.viewer'],
        },
    },
});
