/**
 * @sw-package discovery
 */

Contena.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'content',
    key: 'theme',
    roles: {
        viewer: {
            privileges: [
                'theme:read',
                'theme_child:read',
                'channel:read',
                Contena.Service('privileges').getPrivileges('media.viewer'),
            ],
            dependencies: [],
        },
        editor: {
            privileges: [
                'theme:update',
                'theme_child:update',
                'tag:read',
                'blog_media:read',
                'blog:read',
                'category:read',
                'blog_manufacturer:read',
                'mail_template_media:read',
                'mail_template:read',
                'document_base_config:read',
                'admin_user:read',
                'payment_method:read',
                'shipping_method:read',
                'custom_field_set:read',
                'custom_field:read',
                'custom_field_set_relation:read',
                Contena.Service('privileges').getPrivileges('media.creator'),
            ],
            dependencies: ['theme.viewer'],
        },
        creator: {
            privileges: ['theme:create', 'theme_child:create'],
            dependencies: ['theme.viewer', 'theme.editor'],
        },
        deleter: {
            privileges: ['theme:delete', 'theme_child:delete'],
            dependencies: ['theme.viewer'],
        },
    },
});
