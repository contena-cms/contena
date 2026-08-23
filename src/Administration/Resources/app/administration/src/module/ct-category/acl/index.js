/**
 * @ct-package discovery
 */

Contena.Service('privileges')
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'content',
        key: 'category',
        roles: {
            viewer: {
                privileges: [
                    'category:read',
                    'category_translation:read',
                    Contena.Service('privileges').getPrivileges('media.viewer'),
                    'seo_url:read',
                    'tag:read',
                    'content_layout:read',
                    'category_content_layout:read',
                    'channel:read',
                    'channel_type:read',
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'category:update',
                    'media:delete',
                    'media_thumbnail:delete',
                    Contena.Service('privileges').getPrivileges('media.creator'),
                    'tag:create',
                    'category_tag:create',
                    'category_tag:delete',
                    'seo_url:update',
                ],
                dependencies: [
                    'category.viewer',
                ],
            },
            creator: {
                privileges: [
                    'category:create',
                ],
                dependencies: [
                    'category.viewer',
                    'category.editor',
                ],
            },
            deleter: {
                privileges: [
                    'category:delete',
                ],
                dependencies: [
                    'category.viewer',
                ],
            },
        },
    })
    .addPrivilegeMappingEntry({
        category: 'permissions',
        parent: 'content',
        key: 'landing_page',
        roles: {
            viewer: {
                privileges: [
                    'landing_page:read',
                    'landing_page_translation:read',
                    'landing_page_tag:read',
                    'landing_page_channel:read',
                    'content_layout:read',
                    'landing_page_content_layout:read',
                    Contena.Service('privileges').getPrivileges('media.viewer'),
                    'tag:read',
                    'channel:read',
                    'channel_type:read',
                    'custom_field_set:read',
                    'custom_field:read',
                    'custom_field_set_relation:read',
                ],
                dependencies: [],
            },
            editor: {
                privileges: [
                    'landing_page:update',
                    'landing_page_translation:create',
                    'landing_page_translation:update',
                    Contena.Service('privileges').getPrivileges('media.creator'),
                    'tag:create',
                    'landing_page_tag:create',
                    'landing_page_tag:delete',
                    'landing_page_channel:create',
                    'landing_page_channel:delete',
                    'seo_url:update',
                ],
                dependencies: [
                    'category.viewer',
                ],
            },
            creator: {
                privileges: [
                    'landing_page:create',
                ],
                dependencies: [
                    'landing_page.viewer',
                    'landing_page.editor',
                ],
            },
            deleter: {
                privileges: [
                    'landing_page:delete',
                ],
                dependencies: [
                    'landing_page.viewer',
                ],
            },
        },
    });
