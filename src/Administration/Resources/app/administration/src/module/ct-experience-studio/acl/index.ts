/**
 */
Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'content',
    key: 'experience_studio',
    roles: {
        viewer: {
            privileges: [
                'content_layout:read',
                'channel:read',
                'blog:read',
                'category:read',
                'landing_page:read',
                'blog_content_layout:read',
                'category_content_layout:read',
                'landing_page_content_layout:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'content_layout:update',
                'blog_content_layout:create',
                'blog_content_layout:update',
                'blog_content_layout:delete',
                'category_content_layout:create',
                'category_content_layout:update',
                'category_content_layout:delete',
                'landing_page_content_layout:create',
                'landing_page_content_layout:update',
                'landing_page_content_layout:delete',
            ],
            dependencies: [
                'experience_studio.viewer',
            ],
        },
        creator: {
            privileges: [
                'content_layout:create',
            ],
            dependencies: [
                'experience_studio.viewer',
                'experience_studio.editor',
            ],
        },
        deleter: {
            privileges: [
                'content_layout:delete',
            ],
            dependencies: [
                'experience_studio.viewer',
            ],
        },
    },
});
