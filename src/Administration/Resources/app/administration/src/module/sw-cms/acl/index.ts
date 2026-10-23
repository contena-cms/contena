/**
 * @sw-package discovery
 */
Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'content',
    key: 'cms',
    roles: {
        viewer: {
            privileges: [
                'category:read',
                'category_translation:read',
                'cms_page:read',
                'cms_section:read',
                'cms_block:read',
                'cms_slot:read',
                'landing_page:read',
                'media:read',
                'media_folder:read',
                'media_default_folder:read',
                'channel:read',
                'delivery_time:read',
                'blog:read',
                'blog_media:read',
                'blog_sorting:read',
                'property_group:read',
                'property_group_option:read',
                'blog_cross_selling:read',
                'blog_cross_selling_assigned_products:read',
                'blog_manufacturer:read',
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'cms_page:update',
                'cms_section:create',
                'cms_section:update',
                'cms_section:delete',
                'cms_block:create',
                'cms_block:update',
                'cms_block:delete',
                'cms_slot:create',
                'cms_slot:update',
                'cms_slot:delete',
                Contena.Service('privileges').getPrivileges('media.creator'),
                'currency:read',
                'content_stream:read',
                'blog_manufacturer:read',
                'category:update',
                'landing_page:update',
            ],
            dependencies: ['cms.viewer'],
        },
        creator: {
            privileges: ['cms_page:create'],
            dependencies: ['cms.viewer', 'cms.editor'],
        },
        deleter: {
            privileges: ['cms_page:delete'],
            dependencies: ['cms.viewer'],
        },
    },
});
